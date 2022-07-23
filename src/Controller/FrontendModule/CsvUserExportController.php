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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Date;
use Contao\Environment;
use Contao\File;
use Contao\MemberGroupModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\UserGroupModel;
use Contao\UserRoleModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Haste\Form\Form;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @FrontendModule(CsvUserExportController::TYPE, category="sac_event_tool_frontend_modules")
 */
class CsvUserExportController extends AbstractFrontendModuleController
{
    public const TYPE = 'csv_user_export';
    private const FIELD_DELIMITER = ';';
    private const FIELD_ENCLOSURE = '"';

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private TranslatorInterface $translator;
    private BinaryFileDownload $binaryFileDownload;
    private string $tempDir;
    private string $projectDir;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, TranslatorInterface $translator, BinaryFileDownload $binaryFileDownload, string $tempDir, string $projectDir)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->binaryFileDownload = $binaryFileDownload;
        $this->tempDir = $tempDir;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgument
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $template->form = $this->getForm()->generate();

        return $template->getResponse();
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgument
     */
    private function getForm(): Form
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        $objForm = new Form(
            'form-user-export',
            'POST',
            static fn ($objHaste) => $request->request->get('FORM_SUBMIT') === $objHaste->getFormId()
        );

        $arrUserRoles = [];

        $result = $this->connection->executeQuery('SELECT * FROM tl_user_role ORDER BY sorting');

        while (false !== ($rowUserRole = $result->fetchAssociative())) {
            $arrUserRoles[$rowUserRole['id']] = $rowUserRole['title'];
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
            if ('form-user-export' === $request->request->get('FORM_SUBMIT')) {
                $blnKeepGroupsInOneLine = $request->request->has('keep-groups-in-one-line') && $request->request->get('keep-groups-in-one-line');

                $exportType = $request->request->get('export-type');

                if ('user-role-export' === $request->request->get('export-type')) {
                    $strTable = 'tl_user';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'disable', 'rescissionCause', 'admin', 'leiterQualifikation', 'lastLogin', 'password', 'pwChange', 'userRole'];
                    $strGroupFieldName = 'userRole';
                    $result = $this->connection->executeQuery('SELECT * FROM tl_user ORDER BY lastname, firstname');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $result, UserRoleModel::class, $blnKeepGroupsInOneLine);
                }

                if ('user-group-export' === $request->request->get('export-type')) {
                    $strTable = 'tl_user';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'disable', 'rescissionCause', 'admin', 'lastLogin', 'password', 'pwChange', 'groups'];
                    $strGroupFieldName = 'groups';
                    $result = $this->connection->executeQuery('SELECT * FROM tl_user ORDER BY lastname, firstname');
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $result, UserGroupModel::class, $blnKeepGroupsInOneLine);
                }

                if ('member-group-export' === $request->request->get('export-type')) {
                    $strTable = 'tl_member';
                    $arrFields = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'isSacMember', 'disable', 'rescissionCause', 'sacMemberId', 'login', 'lastLogin', 'groups'];
                    $strGroupFieldName = 'groups';
                    $result = $this->connection->executeQuery('SELECT * FROM tl_member WHERE isSacMember=? ORDER BY lastname, firstname', ['1']);
                    $this->exportTable($exportType, $strTable, $arrFields, $strGroupFieldName, $result, MemberGroupModel::class, $blnKeepGroupsInOneLine);
                }
            }
        }

        return $objForm;
    }

    /**
     * @param $GroupModel
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgument
     */
    private function exportTable(string $type, string $strTable, array $arrFields, string $strGroupFieldName, Result $result, string $GroupModelClassName, bool $blnKeepGroupsInOneLine = false): void
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var UserRoleModel|MemberGroupModel|UserGroupModel $groupModelAdapter */
        $groupModelAdapter = $this->framework->getAdapter($GroupModelClassName);

        $filename = $type.'_'.$dateAdapter->parse('Y-m-d_H-i-s').'.csv';
        $arrData = [];

        // Write headline
        $arrData[] = $this->getHeadline($arrFields, $strTable);

        // Filter by user role
        $blnHasUserRoleFilter = false;

        $arrFilterRoles = [];

        if ($request->request->has('user-roles') && \is_array($request->request->get('user-roles'))) {
            $arrFilterRoles = $request->request->get('user-roles');
            $blnHasUserRoleFilter = true;
        }

        // Write rows
        while (false !== ($rowUser = $result->fetchAssociative())) {
            // Filter by user role
            if ($blnHasUserRoleFilter) {
                $arrUserRoles = $stringUtilAdapter->deserialize($rowUser['userRole'], true);

                if (\count(array_intersect($arrFilterRoles, $arrUserRoles)) < 1) {
                    continue;
                }
            }

            $arrUser = [];

            $hasGroups = false;

            foreach ($arrFields as $field) {
                if ($field === $strGroupFieldName) {
                    $arrGroupsUserBelongsTo = $stringUtilAdapter->deserialize($rowUser[$field], true);

                    if (!empty($arrGroupsUserBelongsTo)) {
                        // Write all groups/roles in one line
                        if ($blnKeepGroupsInOneLine) {
                            $arrUser[] = implode(', ', array_filter(array_map(
                                static function ($id) use ($groupModelAdapter) {
                                    $objGroupModel = $groupModelAdapter->findByPk($id);

                                    if (null !== $objGroupModel) {
                                        if (\strlen((string) $objGroupModel->name)) {
                                            return $objGroupModel->name;
                                        }

                                        return $objGroupModel->title;
                                    }

                                    return '';
                                },
                                $arrGroupsUserBelongsTo
                            )));
                        } else {
                            // Make a row for each group/role
                            $hasGroups = true;

                            foreach ($arrGroupsUserBelongsTo as $groupId) {
                                if ($blnHasUserRoleFilter && \count($arrFilterRoles) > 0) {
                                    if (!\in_array($groupId, $arrFilterRoles, false)) {
                                        continue;
                                    }
                                }

                                $objGroupModel = $groupModelAdapter->findByPk($groupId);

                                if (null !== $objGroupModel) {
                                    if (\strlen((string) $objGroupModel->name)) {
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
                    $arrUser[] = $this->getField($field, $rowUser);
                }
            }

            if (!$hasGroups) {
                $arrData[] = $arrUser;
            }
        }

        // Downlaod data as CSV spreadsheet
        $this->sendToBrowser($arrData, $filename);
    }

    private function getHeadline(array $arrFields, string $strTable): array
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile($strTable);

        // Write headline
        $arrHeadline = [];

        foreach ($arrFields as $field) {
            $fieldname = $this->translator->trans(sprintf('%s.%s.0', $strTable, $field), [], 'contao_default') ?: $field;
            $arrHeadline[] = $fieldname;
        }

        return $arrHeadline;
    }

    private function getField(string $field, array $arrUser): string
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        if ('password' === $field) {
            return '#######';
        }

        if ('lastLogin' === $field) {
            return $dateAdapter->parse('Y-m-d', $arrUser['lastLogin']);
        }

        if ('phone' === $field || 'mobile' === $field) {
            return beautifyPhoneNumber($arrUser[$field]);
        }

        return (string) $arrUser[$field];
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     */
    private function sendToBrowser(array $arrData, string $filename): void
    {
        /** @var Writer $writerAdapter */
        $writerAdapter = $this->framework->getAdapter(Writer::class);

        // Decode html entities and special chars
        $arrFinal = [];

        foreach ($arrData as $arrRow) {
            $arrLine = array_map(
                static fn ($v) => html_entity_decode(htmlspecialchars_decode((string) $v)),
                $arrRow
            );
            $arrFinal[] = $arrLine;
        }

        // Load the CSV document from an empty string
        $csv = $writerAdapter->createFromString();
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->setDelimiter(static::FIELD_DELIMITER);
        $csv->setEnclosure(static::FIELD_ENCLOSURE);
        $csv->insertAll($arrFinal);

        // Save data to temporary file
        $objFile = new File($this->tempDir.'/'.$filename);
        $objFile->write($csv->toString());
        $objFile->close();

        $this->binaryFileDownload->sendFileToBrowser($this->projectDir.'/'.$objFile->path);
    }
}
