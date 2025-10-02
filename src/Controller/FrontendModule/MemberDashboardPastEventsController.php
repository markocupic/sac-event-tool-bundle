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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Validator;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsFrontendModule(MemberDashboardPastEventsController::TYPE, category: 'sac_event_tool_frontend_modules')]
class MemberDashboardPastEventsController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_past_events';

    private FrontendUser|null $user;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ConvertFile $convertFile,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $projectDir,
        private readonly string $sacevtTempDir,
        private readonly string $sacevtEventTemplateCourseConfirmation,
        private readonly string $sacevtEventCourseConfirmationFileNamePattern,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Set adapters
        $inputAdapter = $this->getContaoAdapter(Input::class);

        $this->user = $this->getUserFromToken();

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Print course certificate
        if ('download_course_certificate' === $inputAdapter->get('do') && \strlen($inputAdapter->get('id')) && null !== $this->user) {
            throw new ResponseException($this->downloadCourseCertificate());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        // Set adapters
        $messageAdapter = $this->getContaoAdapter(Message::class);
        $validatorAdapter = $this->getContaoAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->getContaoAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->getContaoAdapter(Controller::class);
        $stringUtilAdapter = $this->getContaoAdapter(StringUtil::class);
        $frontendAdapter = $this->getContaoAdapter(Frontend::class);

        // Handle messages
        if (empty($this->user->email) || !$validatorAdapter->isEmail($this->user->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Add messages to the template
        $this->addMessagesToTemplate($template, $request);

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Get the event type filter from the module model
        $arrEventTypeFilter = $stringUtilAdapter->deserialize($model->eventType, true);

        // Past events
        $arrPastEvents = $calendarEventsMemberModelAdapter->findPastEventsByMemberId($this->user->id, $arrEventTypeFilter);
        $arrEvents = [];

        foreach ($arrPastEvents as $event) {
            // Do only list the event if the user has participated
            if ('member' === $event['role']) {
                if (null !== $event['eventRegistrationModel']) {
                    if (!$event['eventRegistrationModel']->hasParticipated) {
                        continue;
                    }
                }
            }

            if (EventType::COURSE === $event['eventType']) {
                $event['downloadCourseConfirmationLink'] = $frontendAdapter->addToUrl('do=download_course_certificate&amp;id='.$event['registrationId']);
            }
            $arrEvents[] = $event;
        }

        $template->set('arrPastEvents', $arrEvents);

        return $template->getResponse();
    }

    private function getUserFromToken(): FrontendUser|null
    {
        $user = $this->tokenStorage
            ->getToken()
            ?->getUser()
        ;

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    private function downloadCourseCertificate(): BinaryFileResponse
    {
        // Set adapters
        $calendarEventsMemberModelAdapter = $this->getContaoAdapter(CalendarEventsMemberModel::class);
        $inputAdapter = $this->getContaoAdapter(Input::class);
        $memberModelAdapter = $this->getContaoAdapter(MemberModel::class);
        $dateAdapter = $this->getContaoAdapter(Date::class);

        if (null !== $this->user) {
            $registration = $calendarEventsMemberModelAdapter->findById($inputAdapter->get('id'));

            if (null !== $registration) {
                if ((int) $this->user->sacMemberId === (int) $registration->sacMemberId) {
                    $member = $memberModelAdapter->findOneBySacMemberId($this->user->sacMemberId);
                    $startDate = '';
                    $dates = [];
                    $courseId = '';
                    $eventTitle = $registration->eventName;

                    $calendarEvent = $registration->getRelated('eventId');

                    if (null !== $calendarEvent) {
                        $startDate = $dateAdapter->parse('Y', $calendarEvent->startDate);

                        // Build the date array from the event object
                        $dates = array_map(
                            function ($tstamp) {
                                $dateAdapter = $this->getContaoAdapter(Date::class);

                                return $dateAdapter->parse('d.m.Y', $tstamp);
                            },
                            $this->calendarEventsUtil->getEventTimestamps($calendarEvent),
                        );

                        // Course id
                        $courseId = htmlspecialchars(html_entity_decode((string) $calendarEvent->courseId));

                        // Event title
                        $eventTitle = htmlspecialchars(html_entity_decode((string) $calendarEvent->title));
                    }

                    // Log
                    $this->logger?->log(
                        LogLevel::INFO,
                        \sprintf('New event confirmation download. SAC-User-ID: %d. Event-ID: %s.', $member->sacMemberId, $calendarEvent->id),
                        ['contao' => new ContaoContext(__METHOD__, Log::DOWNLOAD_CERTIFICATE_OF_ATTENDANCE)],
                    );

                    $filenamePattern = str_replace('%%d', '%d', $this->sacevtEventCourseConfirmationFileNamePattern);
                    $filename = \sprintf($filenamePattern, $member->sacMemberId, $registration->id, 'docx');
                    $destFilename = Path::makeAbsolute($this->sacevtTempDir.'/'.$filename, $this->projectDir);

                    $docxTemplateSrc = Path::makeAbsolute($this->sacevtEventTemplateCourseConfirmation, $this->projectDir);

                    // Create the PhpWord instance
                    $phpWord = new MsWordTemplateProcessor($docxTemplateSrc, $destFilename);

                    // Replace template vars
                    $phpWord->replace('eventDates', implode(', ', $dates));
                    $phpWord->replace('firstname', htmlspecialchars(html_entity_decode((string) $member->firstname)));
                    $phpWord->replace('lastname', htmlspecialchars(html_entity_decode((string) $member->lastname)));
                    $phpWord->replace('memberId', $member->sacMemberId);
                    $phpWord->replace('eventYear', $startDate);
                    $phpWord->replace('eventId', htmlspecialchars(html_entity_decode((string) $registration->eventId)));
                    $phpWord->replace('eventName', $eventTitle);
                    $phpWord->replace('regId', $registration->id);
                    $phpWord->replace('courseId', $courseId);

                    // Generate the MS Word file and send it to the browser
                    $splFileDocx = $phpWord->generate();

                    // Generate pdf
                    $splFilePdf = $this->convertFile
                        ->file($splFileDocx->getRealPath())
                        ->uncached(false)
                        ->convertTo('pdf')
                    ;

                    return $this->file($splFilePdf->getRealPath());
                }

                throw new \Exception('There was an error while trying to generate the course confirmation.');
            }
        }

        throw new \LogicException('There was an error while trying to generate the course confirmation.');
    }

    /**
     * Add messages from session to template.
     */
    private function addMessagesToTemplate(FragmentTemplate $template, Request $request): void
    {
        $messageAdapter = $this->getContaoAdapter(Message::class);
        $session = $request->getSession();

        if ($messageAdapter->hasInfo()) {
            $template->set('hasInfoMessage', true);
            $message = $session->getFlashBag()->get('contao.FE.info');
            $template->set('infoMessage', $message[0]);
        }

        if ($messageAdapter->hasError()) {
            $template->set('hasErrorMessage', true);
            $message = $session->getFlashBag()->get('contao.FE.error');
            $template->set('errorMessage', $message[0]);
            $template->set('errorMessages', $message);
        }

        $messageAdapter->reset();
    }
}
