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

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment as TwigEnvironment;

/**
 * Class MyCustomController.
 *
 * @Route("/test", name="markocupic_sac_evt_test", defaults={"_scope" = "frontend", "_token_check" = true})
 */
class TestController extends AbstractController
{
    private Connection $connection;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var TwigEnvironment
     */
    private $twig;

    private array $credentials;

    /**
     * MyCustomController constructor.
     */
    public function __construct(Connection $connection, ContaoFramework $framework, TwigEnvironment $twig, array $credentials)
    {
        $this->connection = $connection;
        $this->framework = $framework;
        $this->twig = $twig;
        $this->credentials = $credentials;
    }

    /**
     * Generate the response.
     */
    public function __invoke()
    {
        $this->framework->initialize(true);

        $hostname = $this->credentials['hostname'];
        $connId = ftp_connect($hostname);

        if (false === $connId) {
            $message = sprintf('FTP server %s is not online.', $hostname);
        } else {
            $message = sprintf('FTP server %s is online.', $hostname);
        }

        return new Response($this->twig->render(
            '@MarkocupicSacEventTool/test.html.twig',
            [
                'message' => $message,
            ]
        ));
    }
}
