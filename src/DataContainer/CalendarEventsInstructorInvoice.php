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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Code4Nix\UriSigner\UriSigner;
use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\DataContainer;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Controller\BackendModule\SendTourRapportNotificationController;
use Markocupic\SacEventToolBundle\DocxTemplator\DocumentType;
use Markocupic\SacEventToolBundle\DocxTemplator\Exception\TourRapportGeneratorException;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\DocxTemplator\OutputType;
use Markocupic\SacEventToolBundle\DocxTemplator\TourRapportGenerator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CalendarEventsInstructorInvoice
{
    /**
     * Import the back end user object.
     */
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private Connection $connection,
        private ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private EventMember $eventMember,
        private UriSigner $uriSigner,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private Security $security,
        private TourRapportGenerator $tourRapportGenerator,
        private TranslatorInterface $translator,
        private string $sacevtEventTemplateTourInvoice,
        private string $sacevtEventTemplateTourRapport,
        private string $sacevtEventTourInvoiceFileNamePattern,
        private string $sacevtEventTourRapportFileNamePattern,
    ) {
    }

    /**
     * Check permissions.
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 90)]
    public function checkPermissions(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $action = $request->query->get('action', '');

        $arrOperations = [
            'generateInvoicePdf',
            'generateTourRapportPdf',
            'sendRapport',
        ];

        if ($dc->currentPid) {
            if (\in_array($action, $arrOperations, true)) {
                $objInvoice = CalendarEventsInstructorInvoiceModel::findById($request->query->get('id'));

                if (null !== $objInvoice) {
                    if (null !== $objInvoice->getRelated('pid')) {
                        $objEvent = $objInvoice->getRelated('pid');
                    }
                }
            } else {
                $objEvent = CalendarEventsModel::findById($dc->currentPid);
            }

            if (isset($objEvent)) {
                $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id);

                if (!$blnAllow) {
                    Message::addError('Sie besitzen nicht die nötigen Rechte, um diese Seite zu sehen.');
                    Controller::redirect(System::getReferer());
                }
            }
        }

        $act = $request->query->get('act', '');
        $user = $this->security->getUser();

        switch ($act) {
            case 'select':
            case 'copyAll':
            case 'deleteAll':
            case 'editAll':
            case 'overrideAll':
                Message::addError($this->translator->trans('ERR.actionNotSupported', [], 'contao_default'));
                Controller::redirect(System::getReferer());
            // no break
            case 'edit':
            case 'delete':
                // A common user should not be allowed to edit another user's report
                if (!$this->security->isGranted('ROLE_ADMIN')) {
                    $id = $this->requestStack->getCurrentRequest()->query->get('id');
                    $userPid = (int) $this->connection->fetchOne('SELECT userPid FROM tl_calendar_events_instructor_invoice WHERE id = ?', [$id]);

                    if ((int) $user->id !== $userPid) {
                        throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
                    }
                }
        }
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 80)]
    public function routeActions(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        if (!$id) {
            return;
        }

        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findById($id);

        if (null === $objEventInvoice) {
            return;
        }

        $action = $request->query->get('action');

        if (!$action) {
            return;
        }

        try {
            if ('generateInvoicePdf' === $request->query->get('action')) {
                throw new ResponseException($this->tourRapportGenerator->download(DocumentType::INVOICE, $objEventInvoice, OutputType::PDF, $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern));
            }

            if ('generateTourRapportPdf' === $request->query->get('action')) {
                throw new ResponseException($this->tourRapportGenerator->download(DocumentType::RAPPORT, $objEventInvoice, OutputType::PDF, $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern));
            }
        } catch (ResponseException|TourRapportGeneratorException|\Exception $e) {
            $errMsg = match (true) {
                $e instanceof ResponseException => throw $e,
                $e instanceof TourRapportGeneratorException => $e->getTranslatableText(),
                default => throw $e,
            };

            Message::addError($errMsg);
            Controller::redirect(System::getReferer());
        }
    }

    /**
     * Display a warning if the report form hasn't been filled out.
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 70)]
    public function validateEventReportForm(DataContainer $dc): void
    {
        if (!$dc->currentPid > 0) {
            return;
        }

        $objEvent = CalendarEventsModel::findById($dc->currentPid);

        if (null === $objEvent) {
            return;
        }

        if (!$objEvent->filledInEventReportForm) {
            Message::addError($this->translator->trans('ERR.evt_strn_eventRapportMustFilledOutCorrectly', [], 'contao_default'));
            Controller::redirect(System::getReferer());
        }
    }

    /**
     * Display a warning if the report form hasn't been filled out.
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 70)]
    public function checkBeforeSendTourRapport(DataContainer $dc): void
    {
        if (!$dc->currentPid) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $action = $request->query->get('action', '');

        if ('sendRapport' !== $action) {
            return;
        }

        $objEvent = CalendarEventsModel::findById($dc->currentPid);

        if (null === $objEvent) {
            return;
        }

        if (!$objEvent->filledInEventReportForm) {
            Message::addError($this->translator->trans('ERR.evt_strn_eventRapportMustFilledOutCorrectly', [], 'contao_default'));
            Controller::redirect(System::getReferer());
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 60)]
    public function purgeInvalidInvoices(): void
    {
        $count = $this->connection->executeStatement('
			DELETE FROM tl_calendar_events_instructor_invoice
		   	WHERE NOT EXISTS (
		   		SELECT id FROM tl_user u WHERE u.id = tl_calendar_events_instructor_invoice.userPid
		  	)
	  	');

        if ($count > 0) {
            Controller::reload();
        }
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.sorting.child_record')]
    public function listInvoices(array $row): string
    {
        return '<div class="tl_content_left"><span class="level">Vergütungsformular mit Tourrapport von: '.UserModel::findById($row['userPid'])->name.'</span> <span>['.CalendarEventsModel::findById($row['pid'])->title.']</span></div>';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.edit.button', priority: 90)]
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.delete.button', priority: 90)]
    public function editButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = true;

        $user = $this->security->getUser();

        // A common user should not be allowed to edit or delete another user's rapport
        if (!$this->security->isGranted('ROLE_ADMIN') && (int) $row['userPid'] !== (int) $user->id) {
            $blnAllow = false;
        }

        if (false === $blnAllow) {
            return Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
        }

        $href = Backend::addToUrl($href.'&amp;id='.$row['id']);

        return '<a href="'.StringUtil::specialcharsUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.sendRapport.button', priority: 90)]
    public function sendRapport(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = false;
        $blnRapportNotificationEnabled = false;

        $objEvent = CalendarEventsModel::findById($row['pid']);

        $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);
        $organizers = EventOrganizerModel::findByIds($arrOrganizers);

        if (null !== $organizers) {
            while ($organizers->next()) {
                if ($organizers->enableRapportNotification) {
                    // Only show the icon without a link if rapport notification is disabled in the
                    // organizer model.
                    $blnRapportNotificationEnabled = true;
                }
            }
        }

        if (null !== $objEvent && $objEvent->filledInEventReportForm && $blnRapportNotificationEnabled) {
            $blnAllow = true;
        }

        if (true === $blnAllow) {
            $user = $this->security->getUser();

            // A common user should not be allowed to send another user's report
            if ($this->security->isGranted('ROLE_ADMIN')) {
                $blnAllow = true;
            } elseif ((int) $row['userPid'] !== (int) $user->id) {
                $blnAllow = false;
            }
        }

        if (false === $blnAllow) {
            return Image::getHtml(str_replace('default', 'disabled', $icon), $label).' ';
        }

        // Generate a signed url
        $href = $this->uriSigner->sign($this->router->generate(SendTourRapportNotificationController::class, [
            'rapport_id' => $row['id'],
            'rt' => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
            'sid' => uniqid(),
        ]));

        return '<a href="'.StringUtil::specialcharsUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_calendar_events_instructor_invoice", target="edit.buttons")
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'edit.buttons')]
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit'], $arrButtons['saveNback']);
        }

        return $arrButtons;
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'fields.iban.load')]
    public function getIbanFromUser(mixed $value, DataContainer $dc): mixed
    {
        // Override value from database
        $value = '';

        $objInvoice = CalendarEventsInstructorInvoiceModel::findById($dc->id);

        if (null === $objInvoice) {
            return $value;
        }

        if (!$objInvoice->userPid) {
            return $value;
        }

        $objUser = UserModel::findById($objInvoice->userPid);

        if (null === $objUser) {
            return $value;
        }

        $value = $objUser->iban;
        $objInvoice->iban = $value;
        $objInvoice->save();

        if (!empty($value)) {
            $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['fields']['iban']['eval']['readonly'] = true;
            Message::addInfo($this->translator->trans('MSC.evt_strn_ibanWasTakenFromUserDb', [$objUser->name], 'contao_default'));
        } else {
            Message::addInfo($this->translator->trans('ERR.evt_strn_ibanNotFound', [], 'contao_default'));
        }

        return $value;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'fields.privateArrival.save')]
    public function validatePrivateArrival(int $value, DataContainer $dc): int
    {
        if (!$dc->id || !$dc->activeRecord) {
            return $value;
        }

        $objEvent = CalendarEventsModel::findById($dc->activeRecord->pid);

        if (null === $objEvent) {
            return $value;
        }

        $objEventMember = $this->eventMember->getParticipatedEventMembers($objEvent);

        if (null === $objEventMember) {
            return $value;
        }

        $countParticipants = $objEventMember->count();

        // Count instructors
        $arrInstructors = $this->calendarEventsUtil->getInstructorsAsArray($objEvent);
        $countInstructors = \count($arrInstructors);

        $countParticipantsTotal = $countParticipants + $countInstructors;

        if ($countParticipantsTotal < $value) {
            throw new \Exception($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$value, $countParticipantsTotal], 'contao_default'));
        }

        // Return the processed value
        return $value;
    }
}
