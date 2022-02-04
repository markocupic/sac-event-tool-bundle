<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRapport2Docx;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEventsInstructorInvoice
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private TranslatorInterface $translator;
    private Security $security;
    private EventRapport2Docx $eventRapport2Docx;
    private string $eventTemplateTourInvoice;
    private string $eventTemplateTourRapport;
    private string $eventTourInvoiceFileNamePattern;
    private string $eventTourRapportFileNamePattern;

    /**
     * Import the back end user object.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, Util $util, TranslatorInterface $translator, Security $security, EventRapport2Docx $eventRapport2Docx, string $eventTemplateTourInvoice, string $eventTemplateTourRapport, string $eventTourInvoiceFileNamePattern, string $eventTourRapportFileNamePattern)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->translator = $translator;
        $this->security = $security;
        $this->eventRapport2Docx = $eventRapport2Docx;
        $this->eventTemplateTourInvoice = $eventTemplateTourInvoice;
        $this->eventTemplateTourRapport = $eventTemplateTourRapport;
        $this->eventTourInvoiceFileNamePattern = $eventTourInvoiceFileNamePattern;
        $this->eventTourRapportFileNamePattern = $eventTourRapportFileNamePattern;
    }

    /**
     * Set correct referer.
     *
     * @Callback(table="tl_calendar_events_instructor_invoice", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Check permissions.
     *
     * @Callback(table="tl_calendar_events_instructor_invoice", target="config.onload", priority=90)
     */
    public function checkPermissions(): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        if (\defined('CURRENT_ID') && CURRENT_ID !== '') {
            if ('generateInvoiceDocx' === $request->query->get('action') || 'generateInvoicePdf' === $request->query->get('action') || 'generateTourRapportDocx' === $request->query->get('action') || 'generateTourRapportPdf' === $request->query->get('action')) {
                $objInvoice = CalendarEventsInstructorInvoiceModel::findByPk($request->query->get('id'));

                if (null !== $objInvoice) {
                    if (null !== $objInvoice->getRelated('pid')) {
                        $objEvent = $objInvoice->getRelated('pid');
                    }
                }
            } else {
                $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);
            }

            if (isset($objEvent)) {
                $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objEvent->id);

                if ($objEvent->registrationGoesTo === $user->id) {
                    $blnAllow = true;
                }

                if (!$blnAllow) {
                    Message::addError('Sie besitzen nicht die nötigen Rechte, um diese Seite zu sehen.');
                    Controller::redirect(System::getReferer());
                }
            }
        }
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @Callback(table="tl_calendar_events_instructor_invoice", target="config.onload", priority=80)
     */
    public function routeActions(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findByPk($id);

        if (null !== $objEventInvoice) {
            /** @var EventRapport2Docx $objTemplator */
            $objTemplator = $this->eventRapport2Docx;

            if ('generateInvoiceDocx' === $request->query->get('action')) {
                $objTemplator->generate('invoice', $objEventInvoice, 'docx', $this->eventTemplateTourInvoice, $this->eventTourInvoiceFileNamePattern);
            }

            if ('generateInvoicePdf' === $request->query->get('action')) {
                $objTemplator->generate('invoice', $objEventInvoice, 'pdf', $this->eventTemplateTourInvoice, $this->eventTourInvoiceFileNamePattern);
            }

            if ('generateTourRapportDocx' === $request->query->get('action')) {
                $objTemplator->generate('rapport', $objEventInvoice, 'docx', $this->eventTemplateTourRapport, $this->eventTourRapportFileNamePattern);
            }

            if ('generateTourRapportPdf' === $request->query->get('action')) {
                $objTemplator->generate('rapport', $objEventInvoice, 'pdf', $this->eventTemplateTourRapport, $this->eventTourRapportFileNamePattern);
            }
        }
    }

    /**
     * Display a warning if report form hasn't been filled out.
     *
     * @Callback(table="tl_calendar_events_instructor_invoice", target="config.onload", priority=70)
     */
    public function warnIfReportFormHasNotFilledIn(): void
    {
        if (\defined('CURRENT_ID') && CURRENT_ID !== '') {
            $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);

            if (null !== $objEvent) {
                if (!$objEvent->filledInEventReportForm) {
                    Message::addError('Bevor ein Vergütungsformular erstellt wird, sollte der Rapport vollständig ausgefüllt worden sein.', 'BE');
                    Controller::redirect(System::getReferer());
                }
            }
        }
    }

    /**
     * @throws Exception
     * @Callback(table="tl_calendar_events_instructor_invoice", target="config.onload", priority=60)
     */
    public function reviseTable(): void
    {
        $count = $this->connection->executeStatement('DELETE FROM tl_calendar_events_instructor_invoice WHERE NOT EXISTS (SELECT * FROM tl_user WHERE tl_calendar_events_instructor_invoice.userPid = tl_user.id)');

        if ($count > 0) {
            Controller::reload();
        }
    }

    /**
     * @Callback(table="tl_calendar_events_instructor_invoice", target="list.sorting.child_record")
     */
    public function listInvoices(array $row): string
    {
        return '<div class="tl_content_left"><span class="level">Vergütungsformular (mit Tour Rapport) von: '.UserModel::findByPk($row['userPid'])->name.'</span> <span>['.CalendarEventsModel::findByPk($row['pid'])->title.']</span></div>';
    }

    /**
     * @Callback(table="tl_calendar_events_instructor_invoice", target="edit.buttons")
     */
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit'], $arrButtons['saveNback']);
        }

        return $arrButtons;
    }

    /**
     * @param $value
     *
     * @return mixed|null
     * @Callback(table="tl_calendar_events_instructor_invoice", target="fields.iban.load")
     */
    public function getIbanFromUser($value, DataContainer $dc)
    {
        if ($dc->activeRecord) {
            if (null !== ($objInvoice = CalendarEventsInstructorInvoiceModel::findByPk($dc->activeRecord->id))) {
                if ($objInvoice->userPid > 0 && null !== ($objUser = UserModel::findByPk($objInvoice->userPid))) {
                    if ('' !== $objUser->iban) {
                        if ($value !== $objUser->iban) {
                            $value = $objUser->iban;
                            $objInvoice->iban = $value;
                            $objInvoice->save();
                            Message::addInfo(
                                sprintf(
                                    'Die IBAN Nummer für "%s" wurde aus der Benutzerdatenbank übernommen. Falls die IBAN nicht stimmt, muss diese zuerst unter "Profil" berichtigt werden!',
                                    $objUser->name
                                )
                            );
                        }

                        $GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice']['fields']['iban']['eval']['readonly'] = true;
                    } else {
                        Message::addInfo('Leider wurde für deinen Namen keine IBAN gefunden. Bitte hinterlege deine IBAN in deinem Profil, damit diese in Zukunft automatisch beim Erstellen einer Abrechnung im Feld "IBAN" eingefügt werden kann.');
                    }
                }
            }
        }

        return $value;
    }
}
