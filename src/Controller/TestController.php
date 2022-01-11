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
    /**
     * @var Connection
     */
    private Connection $connection;

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
    public function __construct(Connection $connection, ContaoFramework $framework, TwigEnvironment $twig)
    {
        $this->connection = $connection;
        $this->framework = $framework;
        $this->twig = $twig;
    }

    /**
     * Generate the response.
     */
    public function __invoke()
    {
        $this->framework->initialize(true);

        $schemaManager = $this->connection->getSchemaManager();

        if ($schemaManager->tablesExist('Cars')) {
            return new Response('Table already exists.');
        }

        $table = new Table('Cars');
        $table->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true, "notnull" => true));
        $table->addColumn("brand", "string", array("notnull" => true));
        $table->addColumn("type", "string", array("notnull" => true));
        $table->addColumn("color", "string", array("notnull" => true));

        // Set the pk
        $table->setPrimaryKey(array("id"));

        // Create the table
        $schemaManager->createTable($table);

        return new Response('Successfully created the new table "Cars".');


        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'] = 'ftpserver.sac-cas.ch';
        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'] = 4250;
        //$GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'] = 'Ewawehapi255';
        //$GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS'] = '4250,4251,4252,4253,4254';

        $hostname = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'];
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
