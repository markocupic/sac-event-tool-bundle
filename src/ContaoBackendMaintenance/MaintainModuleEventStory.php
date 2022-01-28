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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintenance;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Folder;
use Contao\Message;
use Contao\PurgeData;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MaintainModuleEventStory
{
    private ContaoFramework $framework;

    private Connection $connection;

    private LoggerInterface $logger;

    private string $projectDir;

    public function __construct(ContaoFramework $framework, Connection $connection, ?LoggerInterface $logger, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Remove image upload folders that aren't assigned to an event story.
     *
     * @throws Exception
     */
    public function run(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Get the image upload path
        $eventStoriesUploadPath = $configAdapter->get('SAC_EVT_EVENT_STORIES_UPLOAD_PATH');

        $fs = new Filesystem();

        $finder = (new Finder())
            ->directories()
            ->depth('< 1')
            ->notName('tmp')
            ->in($this->projectDir.'/'.$eventStoriesUploadPath)
        ;

        if ($finder->hasResults()) {
            foreach ($finder as $objFolder) {
                $path = $objFolder->getRealPath();
                $basename = $objFolder->getBasename();

                if (!$this->connection->fetchOne('SELECT id FROM tl_calendar_events_story WHERE id = ?', [(int) $basename])) {
                    // Log
                    $strText = sprintf('Successfully deleted orphaned event story media folder "%s".', $path);
                    $this->logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]);

                    // Display the confirmation message in the Contao backend maintenance module.
                    Message::addConfirmation($strText, PurgeData::class);

                    // Remove folder from filesystem.
                    $fs->remove($path);
                }
            }
        }

        // Purge the tmp folder
        if (is_dir($this->projectDir.'/'.$eventStoriesUploadPath.'/tmp')) {
            $objFolder = new Folder($eventStoriesUploadPath.'/tmp');
            $objFolder->purge();
        }
    }
}
