<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Controller;

use Contao\Backend;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\InvalidResourceException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\StringUtil;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackSummary;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\UnicodeString;
use Twig\Environment as Twig;

/**
 * Backend module keys of "calendar" (see contao/config/config.php):
 * - showEventFeedbacks: anonymous summary of the feedbacks of an event
 * - showEventFeedbacksAsPdf: the same as PDF (docx template, converted with CloudConvert)
 *
 * Same permissions as the registration list: admin, write access to the event or registration coordinator.
 */
#[Autoconfigure(public: true)]
readonly class EventFeedbackBackendController
{
    public const TEMPLATE = '@MarkocupicSacEventTool/EventFeedback/be_event_feedbacks.html.twig';

    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private ContaoFramework $framework,
        private ConvertFile $convertFile,
        private RequestStack $requestStack,
        private Security $security,
        private Twig $twig,
        #[Autowire(param: 'sacevt.feature.event_feedback.docx_template')]
        private string $docxTemplate,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function getEventFeedbackAction(): Response
    {
        $summary = new FeedbackSummary($this->getEvent());

        return new Response($this->twig->render(self::TEMPLATE, [
            'event' => array_map(static fn ($value): string => StringUtil::revertInputEncoding((string) $value), $summary->getEvent()->row()),
            'feedback_count' => $summary->countFeedbacks(),
            'choice_fields' => $summary->getChoiceFields(),
            'text_fields' => $summary->getTextFields(),
            'pdf_link' => $this->framework->getAdapter(Backend::class)->addToUrl('key=showEventFeedbacksAsPdf'),
        ]));
    }

    public function getEventFeedbackAsPdfAction(): void
    {
        $event = $this->getEvent();
        $summary = new FeedbackSummary($event);

        $template = new MsWordTemplateProcessor(
            Path::makeAbsolute($this->docxTemplate, $this->projectDir),
            Path::makeAbsolute(\sprintf('system/tmp/event_feedback_%s_%s.docx', $event->id, time()), $this->projectDir),
        );

        $template->replace('event_title', $this->escape((string) $event->title));
        $template->replace('event_type', (string) $event->eventType);
        $template->replace('event_id', (string) $event->id);
        $template->replace('event_instructor', $this->calendarEventsUtil->getMainInstructorName($event));

        $eventDates = array_map(static fn ($tstamp): string => date('d.m.Y', (int) $tstamp), $this->calendarEventsUtil->getEventTimestamps($event));
        $template->replace('event_date', implode("\r\n", $eventDates), ['multiline' => true]);

        $template->replace('date', date('d.m.Y'));
        $template->replace('count_fb', (string) $summary->countFeedbacks());
        $template->replace('count_reg', (string) CalendarEventsMemberModel::countBy(['hasParticipated = ?', 'eventId = ?'], ['1', $event->id]));

        foreach ($summary->getChoiceFields() as $field) {
            $text = '';

            foreach ($field['values'] as $value) {
                $text .= \sprintf("%sx %s\r\n", $value['count'], $value['label']);
            }

            $template->createClone('dropdown_label');
            $template->addToClone('dropdown_label', 'dropdown_label', $this->escape($field['label']), ['multiline' => true]);
            $template->addToClone('dropdown_label', 'dropdown_feedback', $this->escape($text), ['multiline' => true]);
        }

        foreach ($summary->getTextFields() as $field) {
            $template->createClone('text_label');
            $template->addToClone('text_label', 'text_label', $this->escape($field['label']), ['multiline' => true]);
            $template->addToClone('text_label', 'text_feedback', $this->escape(implode("\r\n\r\n", $field['values'])), ['multiline' => true]);
        }

        $pdf = $this->convertFile
            ->file($template->generate()->getRealPath())
            ->uncached(true)
            ->convertTo('pdf')
        ;

        $response = new BinaryFileResponse($pdf);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            '',
            (new UnicodeString(basename($pdf->getRealPath())))->ascii()->toString(),
        );

        throw new ResponseException($response);
    }

    private function getEvent(): CalendarEventsModel
    {
        $id = (int) $this->requestStack->getCurrentRequest()?->query->get('id');
        $event = CalendarEventsModel::findById($id);

        if (null === $event) {
            throw new InvalidResourceException(\sprintf('Event with ID %d not found.', $id));
        }

        if (!$this->isAllowed($event)) {
            throw new AccessDeniedException('Not allowed to read the feedbacks of this event.');
        }

        return $event;
    }

    private function isAllowed(CalendarEventsModel $event): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'calendar')) {
            return false;
        }

        return $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $event->id) || (int) $event->registrationGoesTo === (int) $user->id;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars(html_entity_decode($text));
    }
}
