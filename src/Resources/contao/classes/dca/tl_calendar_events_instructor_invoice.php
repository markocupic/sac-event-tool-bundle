<?php

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
     * Onload_callback
     * Route actions
     */
    public function routeActions()
    {
        if (Input::get('action') === 'generateInvoice')
        {
            $this->generateInvoice(Input::get('id'));
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
            $objEvent = CalendarEventsModel::findByPk(CURRENT_ID);
            if ($objEvent !== null)
            {
                $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEvent->id);
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
        return '<div class="tl_content_left"><span class="level">Spesenformular von: ' . UserModel::findByPk($row['userPid'])->name . '</span> <span>[' . CalendarEventsModel::findByPk($row['pid'])->title . ']</span></div>';
    }

    /**
     * @param $id
     * Generate Tourabrechnung
     */
    protected function generateInvoice($id)
    {

        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findByPk($id);
        if ($objEventInvoice !== null)
        {
            // Delete tmp files older the 1 week
            // Get root dir
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $arrScan = scan($rootDir . '/' . Config::get('SAC_EVT_TEMP_PATH'));
            foreach ($arrScan as $file)
            {
                if (is_file($rootDir . '/' . Config::get('SAC_EVT_TEMP_PATH') . '/' . $file))
                {
                    $objFile = new File(Config::get('SAC_EVT_TEMP_PATH') . '/' . $file);
                    if ($objFile !== null)
                    {
                        if ($objFile->mtime + 60 * 60 * 24 * 7 < time())
                        {
                            $objFile->delete();
                        }
                    }
                }
            }

            $objEvent = CalendarEventsModel::findByPk($objEventInvoice->pid);
            $objUser = UserModel::findByPk($objEventInvoice->userPid);
            if ($objEvent !== null && $objUser !== null)
            {
                // Check if tour report has filled in
                if (!$objEvent->filledInEventReportForm)
                {
                    Message::addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
                    Controller::redirect($this->getReferer());
                }

                $objEventMember = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND hasParticipated=?')->execute($objEvent->id, '1');
                if (!$objEventMember->numRows)
                {
                    // Send error message if there are no members assigned to the event
                    Message::addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben.');
                    Controller::redirect($this->getReferer());
                }

                $countParticipants = $objEventMember->numRows;
                $countInstructors = count(StringUtil::deserialize($objEvent->instructor, true));
                $countParticipantsTotal = $countParticipants + $countInstructors;

                $arrData = array();

                // Event data
                $arrData[] = array('key' => 'eventTitle', 'value' => htmlspecialchars(html_entity_decode($objEvent->title)));
                Controller::loadLanguageFile('tl_calendar_events');
                $arrEventTstamps = Markocupic\SacEventToolBundle\CalendarSacEvents::getEventTimestamps($objEvent->id);

                // Generate event duration string
                $arrEventDates = array();
                foreach ($arrEventTstamps as $i => $v)
                {
                    if ((count($arrEventTstamps) - 1) == $i)
                    {
                        $strFormat = 'd.m.Y';
                    }
                    else
                    {
                        $strFormat = 'd.m.';
                    }
                    $arrEventDates[] = Date::parse($strFormat, $v);
                }
                $strEventDuration = implode(', ', $arrEventDates);

                $transport = CalendarEventsJourneyModel::findByPk($objEvent->journey) !== null ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : 'keine Angabe';
                $arrData[] = array('key' => 'eventTransport', 'value' => htmlspecialchars(html_entity_decode($transport)));
                $arrData[] = array('key' => 'eventCanceled', 'value' => $objEvent->eventCanceled ? 'Ja' : 'Nein');
                $arrData[] = array('key' => 'eventHasExecuted', 'value' => $objEvent->tourHasExecutedLikePredicted ? 'Ja' : 'Nein');
                $substitutionText = $objEvent->tourSubstitutionText != '' ? $objEvent->tourSubstitutionText : '---';
                $arrData[] = array('key' => 'eventSubstitutionText', 'value' => htmlspecialchars(html_entity_decode($substitutionText)));
                $arrData[] = array('key' => 'eventDuration', 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->eventDuration)));
                $arrData[] = array('key' => 'eventDates', 'value' => htmlspecialchars(html_entity_decode($strEventDuration)));

                // User
                $arrData[] = array('key' => 'eventInstructorName', 'value' => htmlspecialchars(html_entity_decode($objUser->name)));
                $arrData[] = array('key' => 'eventInstructorStreet', 'value' => htmlspecialchars(html_entity_decode($objUser->street)));
                $arrData[] = array('key' => 'eventInstructorPostalCity', 'value' => htmlspecialchars(html_entity_decode($objUser->postal . ' ' . $objUser->city)));
                $arrData[] = array('key' => 'eventInstructorPhone', 'value' => htmlspecialchars(html_entity_decode($objUser->phone)));
                $arrData[] = array('key' => 'countParticipants', 'value' => htmlspecialchars(html_entity_decode($countParticipantsTotal)));


                $arrData[] = array('key' => 'weatherConditions', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourWeatherConditions)));
                $arrData[] = array('key' => 'avalancheConditions', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourAvalancheConditions)));
                $arrData[] = array('key' => 'specialIncidents', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourSpecialIncidents)));


                $arrFields = array('sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes');
                foreach ($arrFields as $field)
                {
                    $arrData[] = array('key' => $field, 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->{$field})));
                }
                // Calculate car costs
                $carTaxes = 0;
                if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0)
                {
                    $objEventMember = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND hasParticipated=?')->execute($objEvent->id, '1');
                    if ($objEventMember->numRows)
                    {
                        $carTaxes = $objEventInvoice->countCars * 0.6 / $countParticipantsTotal * $objEventInvoice->carTaxesKm;
                    }
                }

                $arrData[] = array('key' => 'carTaxes', 'value' => htmlspecialchars(html_entity_decode(round($carTaxes))));
                $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->roadTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
                $arrData[] = array('key' => 'totalCosts', 'value' => htmlspecialchars(html_entity_decode(round($totalCosts))));

                // Notice
                $notice = $objEventInvoice->notice == '' ? '---' : $objEventInvoice->notice;
                $arrData[] = array('key' => 'notice', 'value' => htmlspecialchars(html_entity_decode($notice)));

                // Iban & account holder
                $arrData[] = array('key' => 'iban', 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->iban)));
                $arrData[] = array('key' => 'accountHolder', 'value' => htmlspecialchars(html_entity_decode($objUser->name)));


                // Member list
                $i = 0;


                $rows = array();

                // TL
                $arrInstructors = StringUtil::deserialize($objEvent->instructor, true);
                if (!empty($arrInstructors) && is_array($arrInstructors))
                {
                    foreach ($arrInstructors as $userId)
                    {
                        $objUserModel = UserModel::findByPk($userId);
                        if ($objUserModel !== null)
                        {
                            $i++;
                            $rows[] = array(
                                array('key' => 'i', 'value' => $i, 'options' => array('multiline' => false)),
                                array('key' => 'role', 'value' => 'TL', 'options' => array('multiline' => false)),
                                array('key' => 'firstname', 'value' => $objUserModel->name, 'options' => array('multiline' => false)),
                                array('key' => 'lastname', 'value' => '', 'options' => array('multiline' => false)),
                                array('key' => 'sacMemberId', 'value' => 'Mitgl. No. ' . $objUserModel->sacMemberId, 'options' => array('multiline' => false)),
                                array('key' => 'isNotSacMember', 'value' => $objUserModel->isSacMember ? ' ' : '!inaktiv/kein Mitglied', 'options' => array('multiline' => false)),
                                array('key' => 'street', 'value' => $objUserModel->street, 'options' => array('multiline' => false)),
                                array('key' => 'postal', 'value' => $objUserModel->postal, 'options' => array('multiline' => false)),
                                array('key' => 'city', 'value' => $objUserModel->city, 'options' => array('multiline' => false)),
                                array('key' => 'emergencyPhone', 'value' => $objUserModel->emergencyPhone, 'options' => array('multiline' => false)),
                                array('key' => 'emergencyPhoneName', 'value' => $objUserModel->emergencyPhoneName, 'options' => array('multiline' => false)),
                                array('key' => 'phone', 'value' => $objUserModel->phone, 'options' => array('multiline' => false)),
                                array('key' => 'email', 'value' => $objUserModel->email, 'options' => array('multiline' => false))
                            );
                        }
                    }
                }

                // TN
                while ($objEventMember->next())
                {
                    $i++;
                    $strIsActiveMember = '!inaktiv/keinMItglied';
                    if($objEventMember->sacMemberId != '')
                    {
                        $objMemberModel = MemberModel::findBySacMemberId($objEventMember->sacMemberId);
                        if($objMemberModel !== null)
                        {
                            if($objMemberModel->isSacMember)
                            {
                                $strIsActiveMember = ' ';
                            }
                        }
                    }
                    $rows[] = array(
                        array('key' => 'i', 'value' => $i, 'options' => array('multiline' => false)),
                        array('key' => 'role', 'value' => 'TN', 'options' => array('multiline' => false)),
                        array('key' => 'firstname', 'value' => $objEventMember->firstname, 'options' => array('multiline' => false)),
                        array('key' => 'lastname', 'value' => $objEventMember->lastname, 'options' => array('multiline' => false)),
                        array('key' => 'sacMemberId', 'value' => 'Mitgl. No. ' . $objEventMember->sacMemberId, 'options' => array('multiline' => false)),
                        array('key' => 'isNotSacMember', 'value' => $strIsActiveMember, 'options' => array('multiline' => false)),
                        array('key' => 'street', 'value' => $objEventMember->street, 'options' => array('multiline' => false)),
                        array('key' => 'postal', 'value' => $objEventMember->postal, 'options' => array('multiline' => false)),
                        array('key' => 'city', 'value' => $objEventMember->city, 'options' => array('multiline' => false)),
                        array('key' => 'phone', 'value' => $objEventMember->phone, 'options' => array('multiline' => false)),
                        array('key' => 'emergencyPhone', 'value' => $objEventMember->emergencyPhone, 'options' => array('multiline' => false)),
                        array('key' => 'emergencyPhoneName', 'value' => $objEventMember->emergencyPhoneName, 'options' => array('multiline' => false)),
                        array('key' => 'email', 'value' => $objEventMember->email, 'options' => array('multiline' => false))
                    );
                }

                // Clone rows
                $arrData[] = array(
                    'clone' => 'i',
                    'rows' => $rows
                );

                // Event instructors
                $arrInstructors = array_map(function ($id) {
                    $objUser = \UserModel::findByPk($id);
                    if ($objUser !== null)
                    {
                        return $objUser->name;
                    }
                }, StringUtil::deserialize($objEvent->instructor, true));
                $arrData[] = array('key' => 'eventInstructors', 'value' => htmlspecialchars(html_entity_decode(implode(', ', $arrInstructors))));

                // Event Id
                $arrData[] = array('key' => 'eventId', 'value' => $objEvent->id);

                // Generate filename
                $filename = sprintf(Config::get('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'), time(), 'docx');

                // Create temporary file path
                new Folder(Config::get('SAC_EVT_TEMP_PATH'));
                Dbafs::addResource(Config::get('SAC_EVT_TEMP_PATH'));

                // Generate docxPhpOffice\PhpWord;
                PhpOffice\PhpWord\TemplateProcessorExtended::create($arrData, Config::get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), Config::get('SAC_EVT_TEMP_PATH'), $filename, false);

                // Generate pdf
                Markocupic\SacEventToolBundle\Services\Pdf\DocxToPdfConversion::convert(Config::get('SAC_EVT_TEMP_PATH') . '/' . $filename, Config::get('SAC_EVT_CLOUDCONVERT_API_KEY'), true);
                exit();
            }
        }
    }
}