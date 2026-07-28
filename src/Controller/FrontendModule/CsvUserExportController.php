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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\MemberGroupModel;
use Contao\ModuleModel;
use Contao\UserGroupModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Export\UserExportHelper;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(CsvUserExportController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_csv_user_export')]
class CsvUserExportController extends AbstractFrontendModuleController
{
    public const string TYPE = 'csv_user_export';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly UserExportHelper $userExportHelper,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $template->set('form', $this->getForm()->generate());

        return $template->getResponse();
    }

    private function getForm(): Form
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        $objForm = $this->createFormInstance(
            'form-user-export',
            'POST',
        );

        $arrUserRoles = $this->userExportHelper->getAvailableUserRoles();

        $objForm->setAction($environmentAdapter->get('uri'));

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
            'options' => $this->userExportHelper->getAvailableUserRoles(),
            'eval' => ['multiple' => true],
        ]);

        $objForm->addFormField('user-groups', [
            'label' => ['Backend-Benutzergruppen-Filter (ODER-Verknüpfung)', ''],
            'inputType' => 'select',
            'options' => $this->userExportHelper->getAvailableUserGroups(),
            'eval' => ['multiple' => true],
        ]);

        $objForm->addFormField('member-groups', [
            'label' => ['Frontend-Benutzergruppen-Filter (ODER-Verknüpfung)', ''],
            'inputType' => 'select',
            'options' => $this->userExportHelper->getAvailableMemberGroups(),
            'eval' => ['multiple' => true],
        ]);

        $objForm->addFormField('keep-groups-in-one-line', [
            'label' => ['', 'Rollen einzeilig darstellen'],
            'inputType' => 'checkbox',
        ]);

        // Add the submit-button
        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            if ('form-user-export' === $request->request->get('FORM_SUBMIT')) {
                $keepRolesInOneLine = !empty($request->request->get('keep-groups-in-one-line'));

                $exportType = $request->request->get('export-type');

                if ('user-role-export' === $exportType) {
                    $tableName = 'tl_user';
					// IMPORTANT: The filter column (userRole) MUST be the last entry.
					$columns = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'disable', 'rescissionCause', 'admin', 'leiterQualifikation', 'lastLogin', 'userRole'];
                    $filterKey = 'userRole';
                    $filterModelFQCN = UserRoleModel::class;
                    $filterRoles = empty($request->request->all()['user-roles']) ? [] : $request->request->all()['user-roles'];

                    $dbalResult = $this->connection->executeQuery(
                        'SELECT * FROM tl_user WHERE disable = 0 AND (start = "" OR start < ?) AND (stop = "" OR stop > ?) ORDER BY lastname, firstname',
                        [
                            time(),
                            time(),
                        ],
                        [
                            Types::INTEGER,
                            Types::INTEGER,
                        ],
                    );

                    throw new ResponseException($this->userExportHelper->exportTable(exportType: $exportType, tableName: $tableName, columns: $columns, filterKey: $filterKey, filterRoles: $filterRoles, dbalResult: $dbalResult, filterModelFQCN: $filterModelFQCN, keepRolesInOneLine: $keepRolesInOneLine));
                }

                if ('user-group-export' === $exportType) {
                    $tableName = 'tl_user';
					// IMPORTANT: The filter column (groups) MUST be the last entry.
					$columns = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'disable', 'rescissionCause', 'admin', 'lastLogin', 'groups'];
                    $filterKey = 'groups';
                    $filterModelFQCN = UserGroupModel::class;
                    $filterRoles = empty($request->request->all()['user-groups']) ? [] : $request->request->all()['user-groups'];

                    $dbalResult = $this->connection->executeQuery(
                        'SELECT * FROM tl_user WHERE disable = 0 AND (start = "" OR start < ?) AND (stop = "" OR stop > ?) ORDER BY lastname, firstname',
                        [
                            time(),
                            time(),
                        ],
                        [
                            Types::INTEGER,
                            Types::INTEGER,
                        ],
                    );

                    throw new ResponseException($this->userExportHelper->exportTable(exportType: $exportType, tableName: $tableName, columns: $columns, filterKey: $filterKey, filterRoles: $filterRoles, dbalResult: $dbalResult, filterModelFQCN: $filterModelFQCN, keepRolesInOneLine: $keepRolesInOneLine));
                }

                if ('member-group-export' === $exportType) {
                    $tableName = 'tl_member';
					// IMPORTANT: The filter column (groups) MUST be the last entry.
					$columns = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'isSacMember', 'disable', 'sacMemberId', 'login', 'lastLogin', 'groups'];
                    $filterKey = 'groups';
                    $filterModelFQCN = MemberGroupModel::class;
                    $filterRoles = empty($request->request->all()['member-groups']) ? [] : $request->request->all()['member-groups'];

                    $dbalResult = $this->connection->executeQuery(
                        'SELECT * FROM tl_member WHERE isSacMember = ? ORDER BY lastname, firstname',
                        [
                            1,
                        ],
                        [
                            Types::INTEGER,
                        ],
                    );

                    throw new ResponseException($this->userExportHelper->exportTable(exportType: $exportType, tableName: $tableName, columns: $columns, filterKey: $filterKey, filterRoles: $filterRoles, dbalResult: $dbalResult, filterModelFQCN: $filterModelFQCN, keepRolesInOneLine: $keepRolesInOneLine));
                }
            }
        }

        return $objForm;
    }

    private function createFormInstance(string $formId, string $method): Form
    {
        return new Form(
            $formId,
            $method,
        );
    }
}
