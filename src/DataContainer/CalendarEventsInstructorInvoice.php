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
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
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
    public const string TABLE = 'tl_calendar_events_instructor_invoice';

    /**
     * Custom actions, triggered by the "action" query parameter.
     */
    private const array CUSTOM_ACTIONS = ['generateInvoicePdf', 'generateTourRapportPdf', 'sendRapport'];

    /**
     * Actions on multiple records are not supported.
     */
    private const array MULTI_RECORD_ACTIONS = ['select', 'copyAll', 'deleteAll', 'editAll', 'overrideAll'];

    private Adapter $calendarEventsModel;

    private Adapter $controller;

    private Adapter $invoiceModel;

    private Adapter $message;

    private Adapter $system;

    private Adapter $userModel;

    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private Connection $connection,
        private ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private ContaoFramework $framework,
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
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->invoiceModel = $this->framework->getAdapter(CalendarEventsInstructorInvoiceModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
    }

    /**
     * Important: For requests without "act" parameter, Contao uses the "id" query
     * parameter as parent id. For the custom actions, $dc->currentPid is therefore
     * the id of the invoice and not the id of the event.
     */
    #[AsCallback(table: self::TABLE, target: 'config.onload', priority: 90)]
    public function checkPermissions(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = (int) $request->query->get('id');
        $action = (string) $request->query->get('action', '');
        $act = (string) $request->query->get('act', '');

        if ('' !== $action && !\in_array($action, self::CUSTOM_ACTIONS, true)) {
            throw new AccessDeniedException(\sprintf('Not supported user action "%s".', $action));
        }

        // Invoice list of an event
        if ('' === $act && '' === $action) {
            $event = $this->calendarEventsModel->findById($dc->currentPid);

            if (!$this->security->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $event)) {
                throw new AccessDeniedException('Access denied!');
            }

            if (!$this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_CREATE, $event)) {
                $GLOBALS['TL_DCA'][self::TABLE]['config']['notCopyable'] = true;
                $GLOBALS['TL_DCA'][self::TABLE]['config']['notCreatable'] = true;
            }
        }

        if ('' !== $action) {
            $this->checkActionPermission($action, $id, $dc);
        }

        if ('' !== $act) {
            $this->checkActPermission($act, $id, $dc);
        }
    }

    #[AsCallback(table: self::TABLE, target: 'config.onload', priority: 80)]
    public function routeActions(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = $request->query->get('id');
        $action = $request->query->get('action');

        if (!$id) {
            return;
        }

        $invoice = $this->invoiceModel->findById($id);

        if (null === $invoice || !$action) {
            return;
        }

        try {
            if ('generateInvoicePdf' === $action) {
                throw new ResponseException($this->tourRapportGenerator->download(DocumentType::INVOICE, $invoice, OutputType::PDF, $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern));
            }

            if ('generateTourRapportPdf' === $action) {
                throw new ResponseException($this->tourRapportGenerator->download(DocumentType::RAPPORT, $invoice, OutputType::PDF, $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern));
            }
        } catch (TourRapportGeneratorException $e) {
            $this->message->addError($e->getTranslatableText());
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Invoices can only be created once the event report form has been filled out.
     */
    #[AsCallback(table: self::TABLE, target: 'config.onload', priority: 70)]
    public function validateEventReportForm(DataContainer $dc): void
    {
        if (!$dc->currentPid) {
            return;
        }

        $event = $this->calendarEventsModel->findById($dc->currentPid);

        if (null !== $event && !$event->filledInEventReportForm) {
            $this->message->addError($this->translator->trans('ERR.evt_strn_eventRapportMustFilledOutCorrectly', [], 'contao_default'));
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Delete invoices of users that no longer exist.
     *
     * @throws Exception
     */
    #[AsCallback(table: self::TABLE, target: 'config.onload', priority: 60)]
    public function purgeInvalidInvoices(): void
    {
        $count = $this->connection->executeStatement('
            DELETE FROM tl_calendar_events_instructor_invoice
            WHERE NOT EXISTS (
                SELECT id FROM tl_user u WHERE u.id = tl_calendar_events_instructor_invoice.userPid
            )
        ');

        if ($count > 0) {
            $this->controller->reload();
        }
    }

    #[AsCallback(table: self::TABLE, target: 'list.sorting.child_record')]
    public function listInvoices(array $row): string
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $userName = $this->userModel->findById($row['userPid'])?->name ?? '';
        $eventTitle = $this->calendarEventsModel->findById($row['pid'])?->title ?? '';

        return '<div class="tl_content_left"><span class="level">Vergütungsformular mit Tourrapport von: '.$stringUtil->specialchars($userName).'</span> <span>['.$stringUtil->specialchars($eventTitle).']</span></div>';
    }

    #[AsCallback(table: self::TABLE, target: 'list.operations.edit.button', priority: 90)]
    public function editButton(DataContainerOperation $operation): void
    {
        $this->disableIfNotGranted(CalendarEventsInstructorInvoiceVoter::CAN_UPDATE, $operation);
    }

    #[AsCallback(table: self::TABLE, target: 'list.operations.delete.button', priority: 90)]
    public function deleteButton(DataContainerOperation $operation): void
    {
        $this->disableIfNotGranted(CalendarEventsInstructorInvoiceVoter::CAN_DELETE, $operation);
    }

    /**
     * The tour report is sent by the SendTourRapportNotificationController.
     */
    #[AsCallback(table: self::TABLE, target: 'list.operations.sendRapport.button', priority: 90)]
    public function sendRapport(DataContainerOperation $operation): void
    {
        if ($this->disableIfNotGranted(CalendarEventsInstructorInvoiceVoter::CAN_SEND, $operation)) {
            return;
        }

        $url = $this->uriSigner->sign($this->router->generate(SendTourRapportNotificationController::class, [
            'rapport_id' => $operation->getRecord()['id'],
            'rt' => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
            'sid' => uniqid(),
        ]));

        $operation->setUrl($this->framework->getAdapter(StringUtil::class)->specialcharsUrl($url));
    }

    /**
     * Only the "save" and "save and close" buttons are supported.
     */
    #[AsCallback(table: self::TABLE, target: 'edit.buttons')]
    public function buttonsCallback(array $buttons, DataContainer $dc): array
    {
        if ('edit' === $this->requestStack->getCurrentRequest()->query->get('act')) {
            unset($buttons['saveNcreate'], $buttons['saveNduplicate'], $buttons['saveNedit'], $buttons['saveNback']);
        }

        return $buttons;
    }

    /**
     * The IBAN is always taken from the user (tl_user.iban).
     */
    #[AsCallback(table: self::TABLE, target: 'fields.iban.load')]
    public function getIbanFromUser(mixed $value, DataContainer $dc): mixed
    {
        $invoice = $this->invoiceModel->findById($dc->id);

        if (null === $invoice || !$invoice->userPid) {
            return '';
        }

        $user = $this->userModel->findById($invoice->userPid);

        if (null === $user) {
            return '';
        }

        $invoice->iban = $user->iban;
        $invoice->save();

        if (!empty($user->iban)) {
            $GLOBALS['TL_DCA'][self::TABLE]['fields']['iban']['eval']['readonly'] = true;
            $this->message->addInfo($this->translator->trans('MSC.evt_strn_ibanWasTakenFromUserDb', [$user->name], 'contao_default'));
        } else {
            $this->message->addInfo($this->translator->trans('ERR.evt_strn_ibanNotFound', [], 'contao_default'));
        }

        return $user->iban;
    }

    /**
     * The number of private arrivals cannot exceed the number of participants and
     * instructors.
     *
     * @throws \Exception
     */
    #[AsCallback(table: self::TABLE, target: 'fields.privateArrival.save')]
    public function validatePrivateArrival(int $value, DataContainer $dc): int
    {
        if (!$dc->id || !$dc->activeRecord) {
            return $value;
        }

        $event = $this->calendarEventsModel->findById($dc->activeRecord->pid);

        if (null === $event) {
            return $value;
        }

        $participants = $this->eventMember->getParticipatedEventMembers($event);

        if (null === $participants) {
            return $value;
        }

        $total = $participants->count() + \count($this->calendarEventsUtil->getInstructorsAsArray($event));

        if ($total < $value) {
            throw new \Exception($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$value, $total], 'contao_default'));
        }

        return $value;
    }

    /**
     * Permissions for the custom actions (download the PDFs, send the tour report).
     */
    private function checkActionPermission(string $action, int $id, DataContainer $dc): void
    {
        if (null === $this->invoiceModel->findById($dc->currentPid)?->getRelated('pid')) {
            throw new \Exception(\sprintf('Event with ID "%s" not found!', $dc->currentPid));
        }

        if ('generateInvoicePdf' === $action || 'generateTourRapportPdf' === $action) {
            if (!$this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_DOWNLOAD, $this->invoiceModel->findById($id))) {
                throw new AccessDeniedException('Not enough permissions to download the tour report or the invoice.');
            }
        }

        if ('sendRapport' === $action) {
            if (!$this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_SEND, $this->invoiceModel->findById($id))) {
                throw new AccessDeniedException('Not enough permissions to send the tour report.');
            }
        }
    }

    /**
     * Permissions for the Contao actions (create, edit, delete, show).
     */
    private function checkActPermission(string $act, int $id, DataContainer $dc): void
    {
        if (\in_array($act, self::MULTI_RECORD_ACTIONS, true)) {
            $this->message->addError($this->translator->trans('ERR.actionNotSupported', [], 'contao_default'));
            $this->controller->redirect($this->system->getReferer());

            return;
        }

        $isGranted = match ($act) {
            'create' => $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_CREATE, $this->calendarEventsModel->findById($id)),
            'edit' => $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_UPDATE, $this->invoiceModel->findById($id)),
            'delete' => $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::CAN_DELETE, $this->invoiceModel->findById($id)),
            'show' => $this->security->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $this->calendarEventsModel->findById($dc->currentPid)),
            default => false,
        };

        if ($isGranted) {
            return;
        }

        if ('create' === $act) {
            throw new AccessDeniedException('Not enough permissions to create a new invoice.');
        }

        throw new AccessDeniedException('Not enough permissions to '.$act.' data record ID '.$id.'.');
    }

    /**
     * Disable the operation if the user is not granted the given permission on the
     * invoice. Returns true if the operation has been disabled.
     */
    private function disableIfNotGranted(string $attribute, DataContainerOperation $operation): bool
    {
        $invoice = $this->invoiceModel->findById($operation->getRecord()['id']);

        if ($this->security->isGranted($attribute, $invoice)) {
            return false;
        }

        $operation->disable();

        return true;
    }
}
