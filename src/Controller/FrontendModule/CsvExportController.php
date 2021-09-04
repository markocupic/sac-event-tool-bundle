<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\CoreBundle\Translation\Translator;
use Contao\Database;
use Contao\Database\Result;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\MemberGroupModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\UserGroupModel;
use Contao\UserModel;
use Contao\UserRoleModel;
use Haste\Form\Form;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CsvExportController.
 *
 * @FrontendModule(CsvExportController::TYPE, category="sac_event_tool_frontend_modules")
 */
class CsvExportController extends AbstractFrontendModuleController
{
    public const TYPE = 'csv_export';
    private const FIELD_DELIMITER = ';';
    private const FIELD_ENCLOSURE = '"';

    /**
     * @var
     */
    protected $objForm;

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @throws Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->generateForm();
        $template->form = $this->objForm;

        return $template->getResponse();
    }

    /**
     * @throws Exception
     */
    private function generateForm(): void
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Form $objForm */
        $objForm = new Form(
            'form-user-export',
            'POST',
            function ($objHaste) {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $arrUserRoles = [];
        $objUserRole = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user_role ORDER BY sorting');

        while ($objUserRole->next()) {
            $arrUserRoles[$objUserRole->id] = $objUserRole->title;
        }

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('export-type', [
            'label' => ['Export auswählen', ''],
            'inputType' => 'select',
            'options' => [
                'user-role-export' => 'Backend-User mit SAC-Benutzerrollen exportieren (tl_user_role)',
                'user-group-export' => 'Backend-User mit Benutzergruppenzugehörigkeit exportieren (tl_user_group)',
                'member-group-export' => 'Frontend-User mit Benutzerzugehörigkeit exportieren (tl_member_group)',
            ],
        ]);

        $objForm->addFormField('user-roles', [
            'label' => ['Benutzerrollen-Filter (ODER-Verknüpfung)', ''],
            'inputType' => 'select',
            'options' => $arrUserRoles,
            'eval' => ['multiple' => true],
        ]);

        $objForm->addFormField('keep-groups-in-one-line', [
            'label' => ['', 'Rollen einzeilig darstellen'],
            'inputType' => 'checkbox',
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            if ('form-user-export' === $inputAdapter->post('FORM_SUBMIT')) {
                $blnKeepGroupsInOneLine = $inputAdapter->post('keep-groups-in-one-line') ? true : false;

                $exportType = $inputAdapter->post('export-type');

                if ('user-role-export' === $inputAdapter->post('export-type')) {
                    $strTable = 'tl_user';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'admin', 'lastLogin', 'password', 'pwChange', 'userRole'];
                    $strGroupFieldName = 'userRole';
                    $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user ORDER BY lastname, firstname');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, UserRoleModel::class, $blnKeepGroupsInOneLine);
                }

                if ('user-group-export' === $inputAdapter->post('export-type')) {
                    $strTable = 'tl_user';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'admin', 'lastLogin', 'password', 'pwChange', 'groups'];
                    $strGroupFieldName = 'groups';
                    $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user ORDER BY lastname, firstname');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, UserGroupModel::class, $blnKeepGroupsInOneLine);
                }

                if ('member-group-export' === $inputAdapter->post('export-type')) {
                    $strTable = 'tl_member';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'isSacMember', 'disable', 'sacMemberId', 'login', 'lastLogin', 'groups'];
                    $strGroupFieldName = 'groups';
                    $objUser = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE isSacMember=? ORDER BY lastname, firstname')->execute('1');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $objUser, MemberGroupModel::class, $blnKeepGroupsInOneLine);
                }
            }
        }

        $this->objForm = $objForm->generate();
    }

    /**
     * @param $GroupModel
     *
     * @throws Exception
     */
    private function exportTable(string $type, string $strTable, array $arrFields, string $strGroupFieldName, Result $objUser, $GroupModel, bool $blnKeepGroupsInOneLine = false): void
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /**
         * @var $groupModelAdapter
         *                         $groupModelAdapter can be instance of Contao\UserRoleModel or Contao\MemberGroupModel or Contao\UserGroupModel
         */
        $groupModelAdapter = $this->get('contao.framework')->getAdapter($GroupModel);

        $filename = $type.'_'.$dateAdapter->parse('Y-m-d_H-i-s').'.csv';
        $arrData = [];

        // Write headline
        $arrData[] = $this->getHeadline($arrFields, $strTable);

        // Filter by user role
        $blnHasUserRoleFilter = false;

        if (!empty($inputAdapter->post('user-roles') && \is_array($inputAdapter->post('user-roles')))) {
            $arrFilterRoles = $inputAdapter->post('user-roles');
            $blnHasUserRoleFilter = true;
        }

        // Write rows
        while ($objUser->next()) {
            // Filter by user role
            if ($blnHasUserRoleFilter) {
                $arrUserRoles = $stringUtilAdapter->deserialize($objUser->userRole, true);

                if (\count(array_intersect($arrFilterRoles, $arrUserRoles)) < 1) {
                    continue;
                }
            }

            $arrUser = [];

            foreach ($arrFields as $field) {
                if ($field === $strGroupFieldName) {
                    $hasGroups = false;
                    $arrGroups = $stringUtilAdapter->deserialize($objUser->{$field}, true);

                    if (\count($arrGroups) > 0) {
                        // Write all the groups/roles in one line
                        if ($blnKeepGroupsInOneLine) {
                            $arrUser[] = implode(', ', array_filter(array_map(
                                static function ($id) use ($groupModelAdapter) {
                                    $objGroupModel = $groupModelAdapter->findByPk($id);

                                    if (null !== $objGroupModel) {
                                        if ('' !== $objGroupModel->name) {
                                            return $objGroupModel->name;
                                        }

                                        return $objGroupModel->title;
                                    }

                                    return '';
                                },
                                $arrGroups
                            )));
                        }
                        // Make a row for each group/role
                        else {
                            $hasGroups = true;

                            foreach ($arrGroups as $groupId) {
                                if ($blnHasUserRoleFilter && \count($arrFilterRoles) > 0) {
                                    if (!\in_array($groupId, $arrFilterRoles, false)) {
                                        continue;
                                    }
                                }
                                $objGroupModel = $groupModelAdapter->findByPk($groupId);

                                if (null !== $objGroupModel) {
                                    if ('' !== $objGroupModel->name) {
                                        $arrUser[] = $objGroupModel->name;
                                    } else {
                                        $arrUser[] = $objGroupModel->title;
                                    }
                                } else {
                                    $arrUser[] = 'Unbekannte Gruppe/Rolle mit ID:'.$groupId;
                                }
                                $arrData[] = $arrUser;
                                array_pop($arrUser);
                            }
                        }
                    } else {
                        $arrUser[] = '';
                    }
                } else {
                    $arrUser[] = $this->getField($field, $objUser);
                }
            }

            if (!$hasGroups) {
                $arrData[] = $arrUser;
            } else {
                $hasGroups = false;
            }
        }

        // Print to screen
        $this->printCsv($arrData, $filename);
    }

    private function getHeadline(array $arrFields, string $strTable): array
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile($strTable);

        /** @var Translator $translator */
        $translator = $this->get('translator');

        // Write headline
        $arrHeadline = [];

        foreach ($arrFields as $field) {
            $fieldname = $translator->trans(sprintf('%s.%s.0', $strTable, $field), [], 'contao_default') ?: $field;
            $arrHeadline[] = $fieldname;
        }

        return $arrHeadline;
    }

    private function getField(string $field, Result $objUser): string
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        if ('password' === $field) {
            $defaultPassword = $configAdapter->get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');

            if (password_verify($defaultPassword, $objUser->password)) {
                // Activate pwchange (=side efect) ;-)
                $objUserModel = UserModel::findByPk($objUser->id);

                if ($objUserModel->sacMemberId > 1) {
                    $objUserModel->pwChange = '1';
                    $objUserModel->save();
                }

                return $defaultPassword;
            }

            return '#######';
        }

        if ('lastLogin' === $field) {
            return Date::parse('Y-m-d', $objUser->lastLogin);
        }

        if ('phone' === $field || 'mobile' === $field) {
            return beautifyPhoneNumber($objUser->{$field});
        }

        return $objUser->{$field};
    }

    /**
     * @throws Exception
     */
    private function printCsv(array $arrData, string $filename): string
    {
        /** @var Writer $writerAdapter */
        $writerAdapter = $this->get('contao.framework')->getAdapter(Writer::class);

        // Convert special chars
        $arrFinal = [];

        foreach ($arrData as $arrRow) {
            $arrLine = array_map(
                static function ($v) {
                    return html_entity_decode(htmlspecialchars_decode((string) $v));
                },
                $arrRow
            );
            $arrFinal[] = $arrLine;
        }

        // Send file to browser
        header('Content-Encoding: UTF-8');
        header('Content-type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename);

        // Load the CSV document from a string
        $csv = $writerAdapter->createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);

        $csv->setDelimiter(static::FIELD_DELIMITER);
        $csv->setEnclosure(static::FIELD_ENCLOSURE);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Returns the CSV document as a string
        echo $csv;

        exit;
    }
}
