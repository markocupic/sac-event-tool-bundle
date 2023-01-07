<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Safe\Exceptions\JsonException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use function Safe\json_encode;

#[AsHook('executePreActions', priority: 100)]
class ExecutePreActionsListener
{
    private ContaoFramework $framework;
    private Connection $connection;
    private RequestStack $requestStack;

    // Adapters
    private Adapter $backendUserAdapter;
    private Adapter $configAdapter;
    private Adapter $dateAdapter;
    private Adapter $memberModelAdapter;
    private Adapter $stringUtilAdapter;
    private Adapter $validatorAdapter;

    public function __construct(ContaoFramework $framework, Connection $connection, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->requestStack = $requestStack;

        // Adapters
        $this->backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->dateAdapter = $this->framework->getAdapter(Date::class);
        $this->memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->validatorAdapter = $this->framework->getAdapter(Validator::class);
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function __invoke(string $strAction = ''): void
    {
        // Get current request
        $request = $this->requestStack->getCurrentRequest();

        // Autocompleter when registrating event members manually in the backend
        if ('autocompleterLoadMemberDataFromSacMemberId' === $strAction) {
            // Output
            $json = ['status' => 'error'];
            $objMemberModel = $this->memberModelAdapter->findOneBySacMemberId(Input::post('sacMemberId'));

            if (null !== $objMemberModel) {
                $json = $objMemberModel->row();
                $json['name'] = $json['firstname'].' '.$json['lastname'];
                $json['username'] = str_replace(' ', '', strtolower($json['name']));
                $json['dateOfBirth'] = $this->dateAdapter->parse($this->configAdapter->get('dateFormat'), $json['dateOfBirth']);
                $json['status'] = 'success';

                // Bin to hex otherwise there will be a json error
                $json['avatar'] = $this->validatorAdapter->isBinaryUuid($json['avatar']) ? $this->stringUtilAdapter->binToUuid($json['avatar']) : '';
                $json['password'] = '';
                $json['sectionId'] = $this->stringUtilAdapter->deserialize($objMemberModel->sectionId, true);

                $html = '<div>';
                $html .= '<h1>Mitglied gefunden</h1>';
                $html .= sprintf('<div>Sollen die Daten von %s %s übernommen werden?</div>', $objMemberModel->firstname, $objMemberModel->lastname);
                $html .= '<button class="tl_button">Ja</button> <button class="tl_button">nein</button>';
                $json['html'] = $html;
            }

            // Send json data to the browser
            $this->_jsonSend($json);
        }

        // editAllNavbarHandler in the Contao backend when using the overrideAll or editAll mode
        if ('editAllNavbarHandler' === $strAction) {
            if ('loadNavbar' === $request->request->get('subaction')) {
                $json = [];
                $json['navbar'] = '';
                $json['status'] = 'error';
                $json['subaction'] = $request->request->get('subaction');

                if (null !== $this->backendUserAdapter->getInstance()) {
                    $objTemplate = new BackendTemplate('be_edit_all_navbar_helper');
                    $json['navbar'] = $objTemplate->parse();
                    $json['status'] = 'success';
                }
                // Send json data to the browser
                $this->_jsonSend($json);
            }

            if ('getSessionData' === $request->request->get('subaction')) {
                $json = [];
                $json['session'] = '';
                $json['status'] = 'error';
                $json['sessionData'] = [];
                $strTable = $request->query->get('table');
                $strKey = '' !== $strTable ? $strTable : '';

                if (($objUser = $this->backendUserAdapter->getInstance()) !== null) {
                    $qb = $this->connection->createQueryBuilder();
                    $qb->select('session')
                        ->from('tl_user', 't')
                        ->where('t.id = :id')
                        ->setParameter('id', $objUser->id)
                        ->setMaxResults(1)
                    ;
                    $result = $qb->executeQuery();

                    if (false !== ($user = $result->fetchAssociative())) {
                        $arrSession = $this->stringUtilAdapter->deserialize($user['session'], true);

                        if (!isset($arrSession['editAllHelper'][$strKey])) {
                            $arrChecked = [];
                        } else {
                            $arrChecked = $arrSession['editAllHelper'][$strKey];
                        }

                        $json['sessionData'] = $arrChecked;
                        $json['status'] = 'success';
                    }
                }
                // Send json data to the browser
                $this->_jsonSend($json);
            }

            if ('saveSessionData' === $request->request->get('subaction')) {
                $json = [];
                $json['status'] = 'error';
                $json['sessionData'] = '';
                $strTable = $request->query->get('table');
                $strKey = '' !== $strTable ? $strTable : '';

                if (($objUser = $this->backendUserAdapter->getInstance()) !== null) {
                    $qb = $this->connection->createQueryBuilder();
                    $qb->select('session')
                        ->from('tl_user', 't')
                        ->where('t.id = :id')
                        ->setParameter('id', $objUser->id)
                        ->setMaxResults(1)
                    ;
                    $result = $qb->executeQuery();

                    if (false !== ($user = $result->fetchAssociative())) {
                        $arrSession = $this->stringUtilAdapter->deserialize($user['session'], true);
                        $arrSession['editAllHelper'][$strKey] = $request->request->get('checkedItems');
                        $json['sessionData'] = $arrSession['editAllHelper'];

                        // Update session
                        $qb = $this->connection->createQueryBuilder();
                        $qb->update('tl_user', 't')
                            ->set('t.session', ':session')
                            ->where('t.id = :id')
                            ->setParameter('id', $objUser->id)
                            ->setParameter('session', serialize($arrSession))
                        ;
                        $result = $qb->executeStatement();
                        $json['affectedRows'] = $result;
                        $json['status'] = 'success';
                    }
                }

                // Send json data to the browser
                $this->_jsonSend($json);
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function _jsonSend(array $json): void
    {
        // !!! Do not use new JsonResponse($json) because session data will be overwritten
        header('Content-Type: application/json');
        header('Status: '.Response::HTTP_OK);
        echo json_encode($json);
        exit();
    }
}
