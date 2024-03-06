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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Controller\BackendModule\SendTourRapportNotificationController;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\DocxTemplator\TourRapportGenerator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CalendarEventsInstructorInvoice
{
    /**
     * Import the back end user object.
     */
    public function __construct(
        private ContaoFramework $framework,
        private RequestStack $requestStack,
        private Connection $connection,
        private TranslatorInterface $translator,
        private Security $security,
        private ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private TourRapportGenerator $tourRapportGenerator,
        private EventMember $eventMember,
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
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        $action = $request->query->get('action', '');

        $arrOperations = [
            'generateInvoiceDocx',
            'generateInvoicePdf',
            'generateTourRapportDocx',
            'generateTourRapportPdf',
            'sendRapport',
        ];

        if ($dc->currentPid) {
            if (\in_array($action, $arrOperations, true)) {
                $objInvoice = CalendarEventsInstructorInvoiceModel::findByPk($request->query->get('id'));

                if (null !== $objInvoice) {
                    if (null !== $objInvoice->getRelated('pid')) {
                        $objEvent = $objInvoice->getRelated('pid');
                    }
                }
            } else {
                $objEvent = CalendarEventsModel::findByPk($dc->currentPid);
            }

            if (isset($objEvent)) {
                $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id);

                if ($objEvent->registrationGoesTo === $user->id) {
                    $blnAllow = true;
                }

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

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 80)]
    public function routeActions(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findByPk($id);

        if (null !== $objEventInvoice) {
            $action = $request->query->get('action');

            if ($action) {
                /** @var TourRapportGenerator $objTemplator */
                $objTemplator = $this->tourRapportGenerator;

                if ('generateInvoiceDocx' === $request->query->get('action')) {
                    throw new ResponseException($objTemplator->download('invoice', $objEventInvoice, 'docx', $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern));
                }

                if ('generateInvoicePdf' === $request->query->get('action')) {
                    throw new ResponseException($objTemplator->download('invoice', $objEventInvoice, 'pdf', $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern));
                }

                if ('generateTourRapportDocx' === $request->query->get('action')) {
                    throw new ResponseException($objTemplator->download('rapport', $objEventInvoice, 'docx', $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern));
                }

                if ('generateTourRapportPdf' === $request->query->get('action')) {
                    throw new ResponseException($objTemplator->download('rapport', $objEventInvoice, 'pdf', $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern));
                }
            }
        }
    }

    /**
     * Display a warning if report form hasn't been filled out.
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 70)]
    public function warnIfReportFormHasNotFilledIn(DataContainer $dc): void
    {
        if ($dc->currentPid) {
            $objEvent = CalendarEventsModel::findByPk($dc->currentPid);

            if (null !== $objEvent) {
                if (!$objEvent->filledInEventReportForm) {
                    Message::addError('Bevor ein Vergütungsformular erstellt werden kann, muss der Rapport vollständig ausgefüllt worden sein.', 'BE');
                    Controller::redirect(System::getReferer());
                }
            }
        }
    }

    /**
     * Display a warning if report form hasn't been filled out.
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 70)]
    public function checkBeforeSendTourRapport(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $action = $request->query->get('action', '');

        if ($dc->currentPid && 'sendRapport' === $action) {
            $objEvent = CalendarEventsModel::findByPk($dc->currentPid);

            if (null !== $objEvent) {
                if (!$objEvent->filledInEventReportForm) {
                    Message::addError('Bevor der Tourrapport und das Vergütungsformular versandt werden können, sollte der Tourrapport vollständig ausgefüllt worden sein.', 'BE');
                    Controller::redirect(System::getReferer());
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'config.onload', priority: 60)]
    public function reviseTable(): void
    {
        $count = $this->connection->executeStatement('DELETE FROM tl_calendar_events_instructor_invoice WHERE NOT EXISTS (SELECT * FROM tl_user WHERE tl_calendar_events_instructor_invoice.userPid = tl_user.id)');

        if ($count > 0) {
            Controller::reload();
        }
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.sorting.child_record')]
    public function listInvoices(array $row): string
    {
        return '<div class="tl_content_left"><span class="level">Vergütungsformular mit Tourrapport von: '.UserModel::findByPk($row['userPid'])->name.'</span> <span>['.CalendarEventsModel::findByPk($row['pid'])->title.']</span></div>';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.edit.button', priority: 90)]
    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.delete.button', priority: 90)]
    public function editButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = true;

        $user = $this->security->getUser();

        // A common user should not be allowed to edit or delete another user's report
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $blnAllow = true;
        } elseif ((int) $row['userPid'] !== (int) $user->id) {
            $blnAllow = false;
        }

        if (false === $blnAllow) {
            return Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
        }

        return '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.sendRapport.button', priority: 90)]
    public function sendRapport(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = false;
        $blnRapportNotificationEnabled = false;

        $objEvent = CalendarEventsModel::findByPk($row['pid']);

        $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);
        $organizers = EventOrganizerModel::findByIds($arrOrganizers);

        if (null !== $organizers) {
            while ($organizers->next()) {
                if ($organizers->enableRapportNotification) {
                    // Only show the icon without a link, if rapport notification is disabled in the organizer model.
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
            return Image::getHtml(str_replace('default', 'brightened', $icon), $label).' ';
        }

        // Generate a signed url
        $href = System::getContainer()->get('code4nix_uri_signer.uri_signer')->sign(System::getContainer()->get('router')->generate(SendTourRapportNotificationController::class, [
            'rapport_id' => $row['id'],
            'rt' => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
            'sid' => uniqid(),
        ]));

        return '<a href="'.$href.'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
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
        $value = '';

        if (null !== ($objInvoice = CalendarEventsInstructorInvoiceModel::findByPk($dc->id))) {
            if ($objInvoice->userPid > 0 && null !== ($objUser = UserModel::findByPk($objInvoice->userPid))) {
                $value = $objUser->iban;
                $objInvoice->iban = $value;
                $objInvoice->save();

                if (!empty($value)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['fields']['iban']['eval']['readonly'] = true;

                    Message::addInfo(
                        sprintf(
                            'Die IBAN Nummer für "%s" wurde aus der Benutzerdatenbank übernommen. Falls die IBAN nicht stimmt, muss diese zuerst unter "Profil" berichtigt werden!',
                            $objUser->name
                        )
                    );
                } else {
                    Message::addInfo('Leider wurde für deinen Namen keine IBAN gefunden. Bitte hinterlege deine IBAN in deinem Profil, damit diese in Zukunft automatisch beim Erstellen einer Abrechnung im Feld "IBAN" eingefügt werden kann.');
                }
            }
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

        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->pid);

        if (null === $objEvent) {
            return $value;
        }

        $objEventMember = $this->eventMember->getParticipatedEventMembers($objEvent);

        if (null === $objEventMember) {
            return $value;
        }

        $countParticipants = $objEventMember->count();

        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        // Count instructors
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent);
        $countInstructors = \count($arrInstructors);

        $countParticipantsTotal = $countParticipants + $countInstructors;

        if ($countParticipantsTotal < (int) $value) {
            throw new \Exception($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$value, $countParticipantsTotal], 'contao_default'));
        }

        // Return the processed value
        return $value;
    }
}
