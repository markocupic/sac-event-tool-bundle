<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Contao\System;
use Contao\Validator;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[AsFrontendModule(MemberDashboardPastEventsController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_past_events')]
class MemberDashboardPastEventsController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_past_events';

    private FrontendUser|null $objUser;
    private FragmentTemplate|null $template;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly ConvertFile $convertFile,
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
        $inputAdapter = $this->framework->getAdapter(Input::class);

        // Get logged in member object
        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->objUser = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Print course certificate
        if ('download_course_certificate' === $inputAdapter->get('do') && \strlen($inputAdapter->get('id')) && null !== $this->objUser) {
            throw new ResponseException($this->downloadCourseCertificate());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        // Set adapters
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $frontendAdapter = $this->framework->getAdapter(Frontend::class);

        // Handle messages
        if (empty($this->objUser->email) || !$validatorAdapter->isEmail($this->objUser->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Get event type filter from module model
        $arrEventTypeFilter = $stringUtilAdapter->deserialize($model->eventType, true);

        // Past events
        $arrPastEvents = $calendarEventsMemberModelAdapter->findPastEventsByMemberId($this->objUser->id, $arrEventTypeFilter);
        $arrEvents = [];

        foreach ($arrPastEvents as $event) {
            // Do only list if member has participated
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

        $this->template->set('arrPastEvents', $arrEvents);

        return $this->template->getResponse();
    }

    /**
     * @throws \Exception
     */
    private function downloadCourseCertificate(): BinaryFileResponse
    {
        // Set adapters
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);

        if (null !== $this->objUser) {
            $objRegistration = $calendarEventsMemberModelAdapter->findByPk($inputAdapter->get('id'));

            if (null !== $objRegistration) {
                if ((int) $this->objUser->sacMemberId === (int) $objRegistration->sacMemberId) {
                    $objMember = $memberModelAdapter->findOneBySacMemberId($this->objUser->sacMemberId);
                    $startDate = '';
                    $arrDates = [];
                    $courseId = '';
                    $eventTitle = $objRegistration->eventName;

                    $objEvent = $objRegistration->getRelated('eventId');

                    if (null !== $objEvent) {
                        $startDate = $dateAdapter->parse('Y', $objEvent->startDate);

                        // Build up $arrData;
                        // Get event dates from event object
                        $arrDates = array_map(
                            function ($tstmp) {
                                $dateAdapter = $this->framework->getAdapter(Date::class);

                                return $dateAdapter->parse('d.m.Y', $tstmp);
                            },
                            $calendarEventsUtilAdapter->getEventTimestamps($objEvent)
                        );

                        // Course id
                        $courseId = htmlspecialchars(html_entity_decode((string) $objEvent->courseId));

                        // Event title
                        $eventTitle = htmlspecialchars(html_entity_decode((string) $objEvent->title));
                    }

                    // Log
                    $this->logger?->log(
                        LogLevel::INFO,
                        sprintf('New event confirmation download. SAC-User-ID: %d. Event-ID: %s.', $objMember->sacMemberId, $objEvent->id),
                        ['contao' => new ContaoContext(__METHOD__, Log::DOWNLOAD_CERTIFICATE_OF_ATTENDANCE)]
                    );

                    $filenamePattern = str_replace('%%d', '%d', $this->sacevtEventCourseConfirmationFileNamePattern);
                    $filename = sprintf($filenamePattern, $objMember->sacMemberId, $objRegistration->id, 'docx');
                    $destFilename = Path::makeAbsolute($this->sacevtTempDir.'/'.$filename, $this->projectDir);

                    $docxTemplateSrc = Path::makeAbsolute($this->sacevtEventTemplateCourseConfirmation, $this->projectDir);

                    // Create PhpWord instance
                    $objPhpWord = new MsWordTemplateProcessor($docxTemplateSrc, $destFilename);

                    // Replace template vars
                    $objPhpWord->replace('eventDates', implode(', ', $arrDates));
                    $objPhpWord->replace('firstname', htmlspecialchars(html_entity_decode((string) $objMember->firstname)));
                    $objPhpWord->replace('lastname', htmlspecialchars(html_entity_decode((string) $objMember->lastname)));
                    $objPhpWord->replace('memberId', $objMember->sacMemberId);
                    $objPhpWord->replace('eventYear', $startDate);
                    $objPhpWord->replace('eventId', htmlspecialchars(html_entity_decode((string) $objRegistration->eventId)));
                    $objPhpWord->replace('eventName', $eventTitle);
                    $objPhpWord->replace('regId', $objRegistration->id);
                    $objPhpWord->replace('courseId', $courseId);

                    // Generate MS Word file and send it to the browser
                    $objSplFileDocx = $objPhpWord->generate();

                    // Generate pdf
                    $objSplFilePdf = $this->convertFile
                        ->file($objSplFileDocx->getRealPath())
                        ->uncached(false)
                        ->convertTo('pdf')
                        ;

                    return $this->file($objSplFilePdf->getRealPath());
                }

                throw new \Exception('There was an error while trying to generate the course confirmation.');
            }
        }

        throw new \LogicException('There was an error while trying to generate the course confirmation.');
    }

    /**
     * Add messages from session to template.
     */
    private function addMessagesToTemplate(): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

        if ($messageAdapter->hasInfo()) {
            $this->template->set('hasInfoMessage', true);
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->template->set('infoMessage', $session[0]);
        }

        if ($messageAdapter->hasError()) {
            $this->template->set('hasErrorMessage', true);
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->template->set('errorMessage', $session[0]);
            $this->template->set('errorMessages', $session);
        }

        $messageAdapter->reset();
    }
}
