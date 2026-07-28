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

namespace Markocupic\SacEventToolBundle\Controller\BackendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\Controller;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Export\UserExportHelper;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/%contao.backend.route_prefix%/sac_backend_user_roles_export', name: self::class, defaults: ['_scope' => 'backend'])]
class SacBackendUserRolesExportController extends AbstractBackendController
{
    public const string BACKEND_MODULE_TYPE = 'sac_backend_user_roles_export';

    public const string BACKEND_MODULE_CATEGORY = 'sac_be_modules';

    private const string EXPORT_TYPE = 'user_role_export';

	// IMPORTANT: The filter column (userRole) MUST be the last entry.
    private const array COLUMNS = ['id', 'lastname', 'firstname', 'gender', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'disable', 'rescissionCause', 'admin', 'leiterQualifikation', 'lastLogin', 'userRole'];

    private const string TABLE_NAME = 'tl_user';

    private const string FILTER_KEY = 'userRole';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly UserExportHelper $userExportHelper,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(): Response
    {
        $this->checkPermission();

        $this->framework->initialize();

        $this->framework->getAdapter(Controller::class)->loadLanguageFile('default');

        $response = $this->render('@MarkocupicSacEventTool/Backend/SacBackendUserRolesExport/sac_backend_user_roles_export.html.twig', [
            'form' => $this->getForm($this->requestStack->getCurrentRequest())->generate(),
        ]);

        $response->headers->set('Turbo-Visit', 'false');

        return $response;
    }

    private function checkPermission(): void
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'sac_pilatus_user_role_export')) {
            return;
        }

        throw new AccessDeniedException('Access denied');
    }

    private function getForm(Request $request): Form
    {
        $form = $this->createFormInstance(formId: 'form-user-export', method: 'GET');
        $form->setAction($request->getUri());

        $form->addFormField('user-roles', [
            'label' => ['Benutzerrollen-Filter (ODER-Verknüpfung)', 'Ohne Selektierung, werden alle Benutzer exportiert'],
            'inputType' => 'select',
            'options' => $this->userExportHelper->getAvailableUserRoles(),
            'eval' => ['chosen' => true, 'multiple' => true],
        ]);

        $form->addFormField('keep-groups-in-one-line', [
            'label' => ['Rollen einzeilig darstellen', 'Rollen einzeilig darstellen'],
            'inputType' => 'checkbox',
        ]);

        $form->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
            'value' => 'Export starten',
            'default' => 'Export starten',
        ]);

        if ($form->validate() && $request->query->has('submit')) {
            $keepRolesInOneLine = !empty($request->query->get('keep-groups-in-one-line'));
            $filterRoles = empty($request->query->all()['user-roles']) ? [] : $request->query->all()['user-roles'];
            $tableName = self::TABLE_NAME;
            $columns = self::COLUMNS;
            $filterKey = self::FILTER_KEY;
            $filterModelFQCN = UserRoleModel::class;

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

            throw new ResponseException($this->userExportHelper->exportTable(exportType: self::EXPORT_TYPE, tableName: $tableName, columns: $columns, filterKey: $filterKey, filterRoles: $filterRoles, dbalResult: $dbalResult, filterModelFQCN: $filterModelFQCN, keepRolesInOneLine: $keepRolesInOneLine));
        }

        return $form;
    }

    private function createFormInstance(string $formId, string $method): Form
    {
        return new Form(
            $formId,
            $method,
        );
    }
}
