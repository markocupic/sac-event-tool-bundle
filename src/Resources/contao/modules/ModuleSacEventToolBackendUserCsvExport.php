<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;


use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Haste\Form\Form;
use League\Csv\Reader;
use League\Csv\Writer;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolBackendUserCsvExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolBackendUserCsvExport extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'sac_event_tool_backend_user_csv_export';

    /**
     * @var
     */
    protected $objForm;

    /**
     * @var string
     */
    protected $strDelimiter = ';';

    /**
     * @var string
     */
    protected $strEnclosure = '"';

    /**
     * @var
     */
    protected $defaultPassword;


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolBackendUserCsvExport'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->defaultPassword = Config::get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->generateForm();
        $this->Template->form = $this->objForm;
    }

    /**
     * @throws \League\Csv\Exception
     * @throws \TypeError
     */
    private function generateForm()
    {
        $objForm = new Form('form-user-export', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });


        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('export-type', array(
            'label'     => array('Export auswählen', ''),
            'inputType' => 'select',
            'options'   => array('user-role-export' => 'SAC Benutzerrollen exportieren', 'user-group-export' => 'User und Benutzergruppenzugehörigkeit exportieren', 'member-group-export' => 'Mitglieder und Benutzerzugehörigkeit exportieren'),
        ));

        $objForm->addFormField('keep-groups-in-one-line', array(
            'label'     => array('', 'Gruppen einzeilig darstellen'),
            'inputType' => 'checkbox',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));


        if ($objForm->validate())
        {
            if (Input::post('FORM_SUBMIT') === 'form-user-export')
            {
                $blnKeepGroupsInOneLine = Input::post('keep-groups-in-one-line');

                $exportType = Input::post('export-type');

                if (Input::post('export-type') === 'user-role-export')
                {
                    $strTable = 'tl_user';
                    $arrFields = array('id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'lastLogin', 'password', 'pwChange', 'userRole');
                    $strGroupFieldName = 'userRole';
                    $objUser = Database::getInstance()->execute('SELECT * FROM tl_user');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, 'UserRoleModel', $blnKeepGroupsInOneLine);
                }

                if (Input::post('export-type') === 'user-group-export')
                {
                    $strTable = 'tl_user';
                    $arrFields = array('id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'lastLogin', 'password', 'pwChange', 'groups');
                    $strGroupFieldName = 'groups';
                    $objUser = Database::getInstance()->execute('SELECT * FROM tl_user');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, 'UserGroupModel', $blnKeepGroupsInOneLine);
                }

                if (Input::post('export-type') === 'member-group-export')
                {
                    $strTable = 'tl_member';
                    $arrFields = array('id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'login', 'lastLogin', 'groups');
                    $strGroupFieldName = 'groups';
                    $objUser = Database::getInstance()->execute('SELECT * FROM tl_member');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, 'MemberGroupModel', $blnKeepGroupsInOneLine);
                }

            }
        }


        $this->objForm = $objForm->generate();
    }

    /**
     * @param $type
     * @param $strTable
     * @param $arrFields
     * @param $strGroupFieldName
     * @param $objUser
     * @param $GroupModel
     * @param bool $blnKeepGroupsInOneLine
     * @throws \League\Csv\Exception
     * @throws \TypeError
     */
    private function exportTable($type, $strTable, $arrFields, $strGroupFieldName, $objUser, $GroupModel, $blnKeepGroupsInOneLine = false)
    {
        $filename = $type . '_' . \Date::parse('Y-m-d_H-i-s') . '.csv';
        $arrData = array();

        // Write headline
        $arrData[] = $this->getHeadline($arrFields, $strTable);

        // Write rows
        while ($objUser->next())
        {
            $arrUser = array();
            foreach ($arrFields as $field)
            {
                if ($field === $strGroupFieldName)
                {
                    $hasGroups = false;
                    $arrGroups = StringUtil::deserialize($objUser->{$field}, true);

                    if (count($arrGroups) > 0)
                    {
                        // Write all the groups/roles in one line
                        if ($blnKeepGroupsInOneLine)
                        {
                            $arrUser[] = implode(', ', array_filter(array_map(function ($id) use ($GroupModel) {
                                $objGroupModel = $GroupModel::findByPk($id);
                                if ($objGroupModel !== null)
                                {
                                    if ($objGroupModel->name != '')
                                    {
                                        return $objGroupModel->name;
                                    }
                                    else
                                    {
                                        return $objGroupModel->title;
                                    }
                                }
                                else
                                {
                                    return '';
                                }
                            }, $arrGroups)));
                        }
                        // Make a row for each group/role
                        else
                        {
                            $hasGroups = true;
                            foreach ($arrGroups as $groupId)
                            {
                                $objGroupModel = $GroupModel::findByPk($groupId);
                                if ($objGroupModel !== null)
                                {
                                    if ($objGroupModel->name != '')
                                    {
                                        $arrUser[] = $objGroupModel->name;
                                    }
                                    else
                                    {
                                        $arrUser[] = $objGroupModel->title;
                                    }
                                }
                                else
                                {
                                    $arrUser[] = 'Unbekannte Gruppe/Rolle mit ID:' . $groupId;
                                }
                                $arrData[] = $arrUser;
                                array_pop($arrUser);
                            }
                        }
                    }
                    else
                    {
                        $arrUser[] = '';
                    }
                }
                else
                {
                    $arrUser[] = $this->getField($field, $objUser);
                }
            }
            if (!$hasGroups)
            {
                $arrData[] = $arrUser;
            }
            else
            {
                $hasGroups = false;
            }
        }

        // Print to screen
        $this->printCsv($arrData, $filename);
    }

    /**
     * @param $arrFields
     * @return array
     */
    private function getHeadline($arrFields, $strTable)
    {
        Controller::loadLanguageFile($strTable);
        // Write headline
        $arrHeadline = array();
        foreach ($arrFields as $field)
        {
            $arrHeadline[] = isset($GLOBALS['TL_LANG'][$strTable][$field][0]) ? $GLOBALS['TL_LANG'][$strTable][$field][0] : $field;
        }
        return $arrHeadline;
    }

    /**
     * @param $field
     * @param $objUser
     * @return string
     */
    private function getField($field, $objUser)
    {
        if ($field === 'password')
        {

            if (password_verify($this->defaultPassword, $objUser->password))
            {
                return $this->defaultPassword;
            }
            else
            {
                return '#######';
            }
        }
        elseif ($field === 'lastLogin')
        {
            return Date::parse('Y-m-d', $objUser->lastLogin);
        }
        else
        {
            return $objUser->{$field};
        }
    }

    /**
     * @param $arrData
     * @param $filename
     * @throws \League\Csv\Exception
     * @throws \TypeError
     */
    private function printCsv($arrData, $filename)
    {
        // Convert special chars
        $arrFinal = array();
        foreach ($arrData as $arrRow)
        {
            $arrLine = array_map(function ($v) {
                return html_entity_decode(htmlspecialchars_decode($v));
            }, $arrRow);
            $arrFinal[] = $arrLine;
        }


        // Send file to browser
        header('Content-Encoding: UTF-8');
        header('Content-type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename);

        // Load the CSV document from a string
        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);

        $csv->setDelimiter($this->strDelimiter);
        $csv->setEnclosure($this->strEnclosure);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Returns the CSV document as a string
        echo $csv;

        exit;
    }
}