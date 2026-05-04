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
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsInstructorInvoiceVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CalendarEventsInstructorInvoice
{
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
        $id = (int) $request->query->get('id');
        $action = (string) $request->query->get('action', '');
        $act = (string) $request->query->get('act', '');
        $customActions = ['generateInvoicePdf', 'generateTourRapportPdf', 'sendRapport'];

        // Check custom actions
        if (!empty($action) && !\in_array($action, $customActions, true)) {
            throw new AccessDeniedException(\sprintf('Not supported user action "%s".', $action));
        }

        if (empty($act) && empty($action)) {
            $eventId = $dc->currentPid;
            $calEvent = CalendarEventsModel::findById($eventId);

            $hasAccess = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $calEvent);

            if (!$hasAccess) {
                throw new AccessDeniedException('Access denied!');
            }

            $canCreate = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_CREATE, $calEvent);

            if (!$canCreate) {
                $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['config']['notCopyable'] = true;
                $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['config']['notCreatable'] = true;
            }
        }

        // Check event-level permissions
        if (!empty($action)) {
            $calEvent = CalendarEventsInstructorInvoiceModel::findById($dc->currentPid)?->getRelated('pid');

            if (null === $calEvent) {
                throw new \Exception(\sprintf('Event with ID "%s" not found!', $dc->currentPid));
            }

            // Allow only admins, event instructors, and special users with permission to open the invoice listing.
            if ('generateInvoicePdf' === $action || 'generateTourRapportPdf' === $action) {
                $invoice = CalendarEventsInstructorInvoiceModel::findById($id);
                $canDownload = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_DOWNLOAD, $invoice);

                if (!$canDownload) {
                    throw new AccessDeniedException('Not enough permissions to download the tour report or the invoice.');
                }
            }

            if ('sendRapport' === $action) {
                $invoice = CalendarEventsInstructorInvoiceModel::findById($id);
                $canSend = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_SEND, $invoice);

                if (!$canSend) {
                    throw new AccessDeniedException('Not enough permissions to send the tour report.');
                }
            }
        }

        // Check action-level permissions
        if (!empty($act)) {
            if (\in_array($act, ['select', 'copyAll', 'deleteAll', 'editAll', 'overrideAll'], true)) {
                Message::addError($this->translator->trans('ERR.actionNotSupported', [], 'contao_default'));
                Controller::redirect(System::getReferer());
            } elseif ('create' === $act) {
                $eventId = $id;
                $calEvent = CalendarEventsModel::findById($eventId);
                $canCreate = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_CREATE, $calEvent);

                if (!$canCreate) {
                    throw new AccessDeniedException('Not enough permissions to create a new invoice.');
                }
            } elseif ('edit' === $act) {
                $invoice = CalendarEventsInstructorInvoiceModel::findById($id);
                $canEdit = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_UPDATE, $invoice);

                if (!$canEdit) {
                    throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
                }
            } elseif ('delete' === $act) {
                $invoice = CalendarEventsInstructorInvoiceModel::findById($id);
                $canDelete = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_DELETE, $invoice);

                if (!$canDelete) {
                    throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
                }
            } elseif ('show' === $act) {
                $calEvent = CalendarEventsModel::findById($dc->currentPid);
                $canShow = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $calEvent);

                if (!$canShow) {
                    throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
                }
            } else {
                throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
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

        $invoice = CalendarEventsInstructorInvoiceModel::findById($id);

        if (null === $invoice) {
            return;
        }

        $action = $request->query->get('action');

        if (!$action) {
            return;
        }

        try {
            if ('generateInvoicePdf' === $request->query->get('action')) {
                $response = $this->tourRapportGenerator->download(DocumentType::INVOICE, $invoice, OutputType::PDF, $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern);

                throw new ResponseException($response);
            }

            if ('generateTourRapportPdf' === $request->query->get('action')) {
                $response = $this->tourRapportGenerator->download(DocumentType::RAPPORT, $invoice, OutputType::PDF, $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern);

                throw new ResponseException($response);
            }
        } catch (\Exception $e) {
            $errMsg = match (true) {
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

        $calEvent = CalendarEventsModel::findById($dc->currentPid);

        if (null === $calEvent) {
            return;
        }

        if (!$calEvent->filledInEventReportForm) {
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

        $calEvent = CalendarEventsModel::findById($dc->currentPid);

        if (null === $calEvent) {
            return;
        }

        if (!$calEvent->filledInEventReportForm) {
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
    public function editButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $invoice = CalendarEventsInstructorInvoiceModel::findById($row['id']);

        $allow = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_UPDATE, $invoice);

        if (!$allow) {
            return Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
        }

        $href = Backend::addToUrl($href.'&amp;id='.$row['id']);

        return '<a href="'.StringUtil::specialcharsUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.delete.button', priority: 90)]
    public function deleteButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $invoice = CalendarEventsInstructorInvoiceModel::findById($row['id']);

        $allow = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_DELETE, $invoice);

        if (!$allow) {
            return Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
        }

        $href = Backend::addToUrl($href.'&amp;id='.$row['id']);

        return '<a href="'.StringUtil::specialcharsUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'list.operations.sendRapport.button', priority: 90)]
    public function sendRapport(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $invoice = CalendarEventsInstructorInvoiceModel::findById($row['id']);

        $allow = $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_SEND, $invoice);

        if (false === $allow) {
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
    public function buttonsCallback(array $buttons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            unset($buttons['saveNcreate'], $buttons['saveNduplicate'], $buttons['saveNedit'], $buttons['saveNback']);
        }

        return $buttons;
    }

    #[AsCallback(table: 'tl_calendar_events_instructor_invoice', target: 'fields.iban.load')]
    public function getIbanFromUser(mixed $value, DataContainer $dc): mixed
    {
        // Override value from database
        $value = '';

        $invoice = CalendarEventsInstructorInvoiceModel::findById($dc->id);

        if (null === $invoice) {
            return $value;
        }

        if (!$invoice->userPid) {
            return $value;
        }

        $user = UserModel::findById($invoice->userPid);

        if (null === $user) {
            return $value;
        }

        $value = $user->iban;
        $invoice->iban = $value;
        $invoice->save();

        if (!empty($value)) {
            $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['fields']['iban']['eval']['readonly'] = true;
            Message::addInfo($this->translator->trans('MSC.evt_strn_ibanWasTakenFromUserDb', [$user->name], 'contao_default'));
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

        $calEvent = CalendarEventsModel::findById($dc->activeRecord->pid);

        if (null === $calEvent) {
            return $value;
        }

        $calEventMember = $this->eventMember->getParticipatedEventMembers($calEvent);

        if (null === $calEventMember) {
            return $value;
        }

        $countParticipants = $calEventMember->count();

        // Count instructors
        $instructors = $this->calendarEventsUtil->getInstructorsAsArray($calEvent);
        $countInstructors = \count($instructors);

        $total = $countParticipants + $countInstructors;

        if ($total < $value) {
            throw new \Exception($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$value, $total], 'contao_default'));
        }

        // Return the processed value
        return $value;
    }
}
