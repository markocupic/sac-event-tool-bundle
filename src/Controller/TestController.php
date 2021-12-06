<?php

declare(strict_types=1);

/*
 * This file is part of Contao Test Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-test-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Class MyCustomController
 *
 * @Route("/test", name="markocupic_sac_evt_test", defaults={"_scope" = "frontend", "_token_check" = true})
 */
class TestController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var TwigEnvironment
     */
    private $twig;

    /**
     * MyCustomController constructor.
     */
    public function __construct(ContaoFramework $framework, TwigEnvironment $twig)
    {
        $this->framework = $framework;
        $this->twig = $twig;
    }

    /**
     * Generate the response
     */
    public function __invoke()
    {

        $this->framework->initialize(true);


        //die($GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME']);
        //die((string)Config::get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'));
        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'] = 'ftpserver.sac-cas.ch';
        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'] = 4250;
        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'] = 'Ewawehapi255';
        //$GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS'] = '4250,4251,4252,4253,4254';

        $hostname = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'];
        $connId = ftp_connect($hostname);

        if(false === $connId)
        {
            die('Error');
        }


        die(print_r($connId,true));

        $animals = [

            [
                'species' => 'dogs',
                'color'   => 'white'
            ],
            [
                'species' => 'birds',
                'color'   => 'black'
            ], [
                'species' => 'cats',
                'color'   => 'pink'
            ], [
                'species' => 'cows',
                'color'   => 'yellow'
            ],
        ];

        return new Response($this->twig->render(
            '@MarkocupicSacEventTool/test.html.twig',
            [
                'animals' => $animals,
            ]
        ));
    }
}

