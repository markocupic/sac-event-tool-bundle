<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_calendar_events_instructor_invoice
 */
class tl_calendar_events_instructor_invoice extends Backend
{


    /**
     * Import the back end user object
     */
    public function __construct()
    {
        // Set correct referer
        if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('ref') != '')
        {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');
            if (isset($session[$ref]['tl_calendar_container']))
            {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar']))
            {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events']))
            {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events_instructor_invoice']))
            {
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
     * Delete orphaned records
     */
    public function reviseTable()
    {
        $reload = false;

        // Delete orphaned records
        $objStmt = $this->Database->execute('DELETE FROM tl_calendar_events_instructor_invoice WHERE NOT EXISTS (SELECT * FROM tl_user WHERE tl_calendar_events_instructor_invoice.userPid = tl_user.id)');
        if ($objStmt->affectedRows > 0)
        {
            $reload = true;
        }

        if ($reload)
        {
            $this->reload();
        }
    }

    /**
     * Onload_callback
     * Route actions
     */
    public function routeActions()
    {
        if (Input::get('action') === 'generateInvoiceDocx')
        {
            $objRapport = new Markocupic\SacEventToolBundle\EventRapport();
            $objRapport->generateInvoice(Input::get('id'), 'docx');
        }

        if (Input::get('action') === 'generateInvoicePdf')
        {
            $objRapport = new Markocupic\SacEventToolBundle\EventRapport();
            $objRapport->generateInvoice(Input::get('id'), 'pdf');
        }
    }


    /**
     * Onload_callback
     * Check if user has enough access rights
     */
    public function checkAccesRights()
    {

        if (CURRENT_ID != '')
        {
            if (Input::get('action') === 'generateInvoiceDocx' || Input::get('action') === 'generateInvoicePdf')
            {
                $objInvoice = \Contao\CalendarEventsInstructorInvoiceModel::findByPk(Input::get('id'));
                if ($objInvoice !== null)
                {
                    if ($objInvoice->getRelated('pid') !== null)
                    {
                        $objEvent = $objInvoice->getRelated('pid');
                    }
                }
            }
            else
            {
                $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);
            }

            if ($objEvent !== null)
            {
                $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEvent->id);
                if ($objEvent->registrationGoesTo === $this->User->id)
                {
                    $blnAllow = true;
                }

                if (!$blnAllow)
                {
                    Message::addError('Sie besitzen nicht die n&ouml;tigen Rechte, um diese Seite zu sehen.', 'BE');
                    $this->redirect($this->getReferer());
                }
            }
        }
    }

    /**
     * Onload_callback
     * Show warning if report form is not filled in
     */
    public function warnIfReportFormHasNotFilledIn()
    {

        if (CURRENT_ID != '')
        {
            $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);
            if ($objEvent !== null)
            {
                if (!$objEvent->filledInEventReportForm)
                {
                    Message::addError('Bevor ein Verg&uuml;tungsformular erstellt wird, sollte der Rapport vollst&auml;ndig ausgef&uuml;llt worden sein.', 'BE');
                    $this->redirect($this->getReferer());
                }
            }
        }
    }

    /**
     * List a style sheet
     *
     * @param array $row
     *
     * @return string
     */
    public function listInvoices($row)
    {
        return '<div class="tl_content_left"><span class="level">Verg&uuml;tungsformular (mit Tour Rapport) von: ' . UserModel::findByPk($row['userPid'])->name . '</span> <span>[' . CalendarEventsModel::findByPk($row['pid'])->title . ']</span></div>';
    }


    /**
     * buttons_callback buttonsCallback
     * @param $arrButtons
     * @param $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, $dc)
    {

        if (\Contao\Input::get('act') === 'edit')
        {
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['saveNduplicate']);
            unset($arrButtons['saveNedit']);
            unset($arrButtons['saveNback']);

        }

        return $arrButtons;
    }
}