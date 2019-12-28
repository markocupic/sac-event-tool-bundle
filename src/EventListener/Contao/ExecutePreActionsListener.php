<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ExecutePreActionsListener
 * @package Markocupic\SacEventToolBundle\EventListener\Contao
 */
class ExecutePreActionsListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * ExecutePreActionsListener constructor.
     * @param ContaoFramework $framework
     * @param Connection $connection
     * @param RequestStack $requestStack
     */
    public function __construct(ContaoFramework $framework, Connection $connection, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $strAction
     */
    public function onExecutePreActions($strAction = '')
    {
        // Set adapters
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);

        // Get current request
        $request = $this->requestStack->getCurrentRequest();

        // Autocompleter when registrating event members manually in the backend
        if ($strAction === 'autocompleterLoadMemberDataFromSacMemberId')
        {
            // Output
            $json = array('status' => 'error');
            $objMemberModel = $memberModelAdapter->findBySacMemberId(Input::post('sacMemberId'));
            if ($objMemberModel !== null)
            {
                $json = $objMemberModel->row();
                $json['name'] = $json['firstname'] . ' ' . $json['lastname'];
                $json['username'] = str_replace(' ', '', strtolower($json['name']));
                $json['dateOfBirth'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $json['dateOfBirth']);
                $json['status'] = 'success';
                // Bin to hex otherwise there will be a json error
                $json['avatar'] = $json['avatar'] != '' ? $stringUtilAdapter->binToUuid($json['avatar']) : '';
                $json['password'] = '';

                $html = '<div>';
                $html .= '<h1>Mitglied gefunden</h1>';
                $html .= sprintf('<div>Sollen die Daten von %s %s &uuml;bernommen werden?</div>', $objMemberModel->firstname, $objMemberModel->lastname);
                $html .= '<button class="tl_button">Ja</button> <button class="tl_button">nein</button>';
                $json['html'] = $html;
            }

            // Send json data to the browser
            $this->_jsonSend($json);
        }

        // editAllNavbarHandler in the Contao backend when using the overrideAll or editAll mode
        if ($strAction === 'editAllNavbarHandler')
        {
            if ($request->request->get('subaction') === 'loadNavbar')
            {
                $json = array();
                $json['navbar'] = '';
                $json['status'] = 'error';
                $json['subaction'] = $request->request->get('subaction');
                if (($objUser = $backendUserAdapter->getInstance()) !== null)
                {
                    /** @var  BackendTemplate $objTemplate */
                    $objTemplate = new BackendTemplate('be_edit_all_navbar_helper');
                    $json['navbar'] = $objTemplate->parse();
                    $json['status'] = 'success';
                }
                // Send json data to the browser
                $this->_jsonSend($json);
            }

            if ($request->request->get('subaction') === 'getSessionData')
            {
                $json = array();
                $json['session'] = '';
                $json['status'] = 'error';
                $json['sessionData'] = array();
                $strTable = $request->query->get('table');
                $strKey = $strTable != '' ? $strTable : '';
                if (($objUser = $backendUserAdapter->getInstance()) !== null)
                {
                    /** @var  Doctrine\DBAL\Query\QueryBuilder $qb */
                    $qb = $this->connection->createQueryBuilder();
                    $qb->select('session')
                        ->from('tl_user', 't')
                        ->where('t.id = :id')
                        ->setParameter('id', $objUser->id)
                        ->setMaxResults(1);
                    $result = $qb->execute();

                    if (false !== ($user = $result->fetch()))
                    {
                        $arrSession = $stringUtilAdapter->deserialize($user['session'], true);
                        if (!isset($arrSession['editAllHelper'][$strKey]))
                        {
                            $arrChecked = array();
                        }
                        else
                        {
                            $arrChecked = $arrSession['editAllHelper'][$strKey];
                        }

                        $json['sessionData'] = $arrChecked;
                        $json['status'] = 'success';
                    }
                }
                // Send json data to the browser
                $this->_jsonSend($json);
            }

            if ($request->request->get('subaction') === 'saveSessionData')
            {
                $json = array();
                $json['status'] = 'error';
                $json['sessionData'] = '';
                $strTable = $request->query->get('table');
                $strKey = $strTable != '' ? $strTable : '';
                if (($objUser = $backendUserAdapter->getInstance()) !== null)
                {
                    /** @var  Doctrine\DBAL\Query\QueryBuilder $qb */
                    $qb = $this->connection->createQueryBuilder();
                    $qb->select('session')
                        ->from('tl_user', 't')
                        ->where('t.id = :id')
                        ->setParameter('id', $objUser->id)
                        ->setMaxResults(1);
                    $result = $qb->execute();

                    if (false !== ($user = $result->fetch()))
                    {
                        $arrSession = $stringUtilAdapter->deserialize($user['session'], true);
                        $arrSession['editAllHelper'][$strKey] = $request->request->get('checkedItems');
                        $json['sessionData'] = $arrSession['editAllHelper'];

                        // Update session
                        /** @var  Doctrine\DBAL\Query\QueryBuilder $qb */
                        $qb = $this->connection->createQueryBuilder();
                        $qb->update('tl_user', 't')
                            ->set('t.session', ':session')
                            ->where('t.id = :id')
                            ->setParameter('id', $objUser->id)
                            ->setParameter('session', serialize($arrSession));
                        $result = $qb->execute();
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
     * @param $json
     * @param int $status
     */
    private function _jsonSend($json, $status = 200)
    {
        // !!! Do not use new JsonResponse($json) because session data will be overwritten
        // Send json data to the browser
        header('Content-Type: application/json');
        header('Status: ' . $status);
        // return the encoded json
        echo json_encode($json);
        exit();
    }
}


