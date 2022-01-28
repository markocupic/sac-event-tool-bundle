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

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\DataContainer;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRapport2Docx;

/**
 * Class TlCalendarEventsInstructorInvoice.
 */
class TlCalendarEventsInstructorInvoice extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        // Set correct referer
        if ('sac_calendar_events_tool' === Input::get('do') && '' !== Input::get('ref')) {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');

            if (isset($session[$ref]['tl_calendar_container'])) {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar'])) {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events'])) {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events_instructor_invoice'])) {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        $this->import('Database');
        $this->import('BackendUser', 'User');

        return parent::__construct();
    }

    /**
     * onload_callback
     * Delete orphaned records.
     */
    public function reviseTable(): void
    {
        $reload = false;

        // Delete orphaned records
        $objStmt = $this->Database->execute('DELETE FROM tl_calendar_events_instructor_invoice WHERE NOT EXISTS (SELECT * FROM tl_user WHERE tl_calendar_events_instructor_invoice.userPid = tl_user.id)');

        if ($objStmt->affectedRows > 0) {
            $reload = true;
        }

        if ($reload) {
            $this->reload();
        }
    }

    /**
     * Onload_callback
     * Route actions.
     */
    public function routeActions(): void
    {
        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findByPk(Input::get('id'));

        if (null !== $objEventInvoice) {
            /** @var EventRapport2Docx $objTemplator */
            $objTemplator = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\EventRapport2Docx');

            if ('generateInvoiceDocx' === Input::get('action')) {
                $objTemplator->generate('invoice', $objEventInvoice, 'docx', Config::get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), Config::get('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'));
            }

            if ('generateInvoicePdf' === Input::get('action')) {
                $objTemplator->generate('invoice', $objEventInvoice, 'pdf', Config::get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), Config::get('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'));
            }

            if ('generateTourRapportDocx' === Input::get('action')) {
                $objTemplator->generate('rapport', $objEventInvoice, 'docx', Config::get('SAC_EVT_EVENT_RAPPORT_TOUR_TEMPLATE_SRC'), Config::get('SAC_EVT_EVENT_TOUR_RAPPORT_FILE_NAME_PATTERN'));
            }

            if ('generateTourRapportPdf' === Input::get('action')) {
                $objTemplator->generate('rapport', $objEventInvoice, 'pdf', Config::get('SAC_EVT_EVENT_RAPPORT_TOUR_TEMPLATE_SRC'), Config::get('SAC_EVT_EVENT_TOUR_RAPPORT_FILE_NAME_PATTERN'));
            }
        }
    }

    /**
     * Onload_callback
     * Check if user has enough access rights.
     */
    public function checkAccesRights(): void
    {
        if (CURRENT_ID !== '') {
            if ('generateInvoiceDocx' === Input::get('action') || 'generateInvoicePdf' === Input::get('action') || 'generateTourRapportDocx' === Input::get('action') || 'generateTourRapportPdf' === Input::get('action')) {
                $objInvoice = CalendarEventsInstructorInvoiceModel::findByPk(Input::get('id'));

                if (null !== $objInvoice) {
                    if (null !== $objInvoice->getRelated('pid')) {
                        $objEvent = $objInvoice->getRelated('pid');
                    }
                }
            } else {
                $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);
            }

            if (null !== $objEvent) {
                $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEvent->id);

                if ($objEvent->registrationGoesTo === $this->User->id) {
                    $blnAllow = true;
                }

                if (!$blnAllow) {
                    Message::addError('Sie besitzen nicht die n&ouml;tigen Rechte, um diese Seite zu sehen.', 'BE');
                    $this->redirect($this->getReferer());
                }
            }
        }
    }

    /**
     * Onload_callback
     * Show warning if report form is not filled in.
     */
    public function warnIfReportFormHasNotFilledIn(): void
    {
        if (CURRENT_ID !== '') {
            $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);

            if (null !== $objEvent) {
                if (!$objEvent->filledInEventReportForm) {
                    Message::addError('Bevor ein Verg&uuml;tungsformular erstellt wird, sollte der Rapport vollst&auml;ndig ausgef&uuml;llt worden sein.', 'BE');
                    $this->redirect($this->getReferer());
                }
            }
        }
    }

    /**
     * List a style sheet.
     *
     * @param array $row
     *
     * @return string
     */
    public function listInvoices($row)
    {
        return '<div class="tl_content_left"><span class="level">Verg&uuml;tungsformular (mit Tour Rapport) von: '.UserModel::findByPk($row['userPid'])->name.'</span> <span>['.CalendarEventsModel::findByPk($row['pid'])->title.']</span></div>';
    }

    /**
     * buttons_callback buttonsCallback.
     *
     * @param $arrButtons
     * @param $dc
     *
     * @return mixed
     */
    public function buttonsCallback($arrButtons, $dc)
    {
        if ('edit' === Input::get('act')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit'], $arrButtons['saveNback']);
        }

        return $arrButtons;
    }

    /**
     * load callback for tl_calendar_events_instructor_invoice.iban.
     *
     * @param $value
     *
     * @return mixed
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
