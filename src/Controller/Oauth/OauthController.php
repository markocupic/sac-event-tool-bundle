<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Oauth;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\BackendUser;
use Contao\PageModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Markocupic\SacEventToolBundle\OpenIdConnect\Authentication\Authentication;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OauthController
 * Enable SSO with sac-cas.ch
 * @package Markocupic\SacEventToolBundle\Controller\Oauth
 */
class OauthController extends AbstractController
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Authentication
     */
    private $authentication;

    /**
     * OauthController constructor.
     * @param ContaoFramework $framework
     * @param Authentication $authentication
     */
    public function __construct(ContaoFramework $framework, Authentication $authentication)
    {
        $this->framework = $framework;
        $this->authentication = $authentication;

        $this->framework->initialize();
    }

    /**
     * @return Response
     * @throws \Exception
     * @Route("/oauth/frontend", name="sac_event_tool_oauth_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function frontendUserAuthenticationAction(): Response
    {
        return new Response('This extension is under construction.', 200);

        // Retrieve the username from openid connect
        $username = 'xxxxxxxxxxxxxxxxx';

        $userClass = FrontendUser::class;

        $providerKey = Authentication::SECURED_AREA_FRONTEND;

        // Authenticate user
        $this->authentication->authenticate($username, $userClass, $providerKey);

        /** @var  PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Redirect to users profile
        $objPage = $pageModelAdapter->findByIdOrAlias('member-profile');
        if ($objPage !== null)
        {
            /** @var  Controller $controllerAdapter */
            $controllerAdapter = $this->framework->getAdapter(Controller::class);
            $controllerAdapter->redirect($objPage->getFrontendUrl());
        }

        return new Response(
            'Successfully logged in.',
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * @return Response
     * @throws \Exception
     * @Route("/oauth/backend", name="sac_event_tool_oauth_backend", defaults={"_scope" = "backend", "_token_check" = false})
     */
    public function backendUserAuthenticationAction(): Response
    {
        return new Response('This extension is under construction.', 200);

        // Retrieve the username from openid connect
        $username = 'xxxxxxxxxxxx';

        $userClass = BackendUser::class;

        $providerKey = Authentication::SECURED_AREA_BACKEND;

        // Authenticate user
        $this->authentication->authenticate($username, $userClass, $providerKey);

        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->redirect('contao');

        return new Response(
            'Successfully logged in.',
            Response::HTTP_UNAUTHORIZED
        );
    }
}
