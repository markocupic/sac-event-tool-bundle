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

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(SwisstopoEventMapController::TYPE, category: 'sac_event_tool_content_elements')]
class SwisstopoEventMapController extends AbstractContentElementController
{
    public const string TYPE = 'swisstopo_event_map';

    private const string ICON_DIR = 'public/icons/swisstopo/tourtypes/svg';

    private const string ICON_ASSET_PATH = 'icons/swisstopo/tourtypes/svg';

    private const string TOUR_TYPE_TABLE = 'tl_tour_type';

    private const string EVENT_ORGANIZER_TABLE = 'tl_event_organizer';

    private const string EVENT_TYPE_TABLE = 'tl_event_type';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly Packages $packages,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $template->set('swisstopoCenter', preg_replace('/\s+/', '', $model->swisstopoCenter));
        $template->set('swisstopoZoom', $model->swisstopoZoom);
        $template->set('date_start', Date::parse('Y-m-d'));
        $template->set('date_end', Date::parse('Y-m-d', (new \DateTime('+2 years'))->getTimestamp()));
        $template->set('limit', 10000);
        $template->set('tour_type_icons', $this->getTourTypeIcons());
        $template->set('tour_type_names', $this->getTitles(self::TOUR_TYPE_TABLE));
        $template->set('event_organizer_names', $this->getTitles(self::EVENT_ORGANIZER_TABLE));
        $template->set('event_type_names', $this->getEventTypeTitles());

        return $template->getResponse();
    }

    /**
     * Maps a tour type ID to the public URL of its icon.
     *
     * The file name is expected to start with the tour type ID,
     * e.g. "2-ht.svg" belongs to tour type 2. Dropping a new file
     * into the directory is therefore enough, no code change needed.
     *
     * @return array<int, string>
     */
    private function getTourTypeIcons(): array
    {
        $dir = Path::join(\dirname(__DIR__, 3), self::ICON_DIR);

        $icons = [];

        foreach (glob($dir.'/*.svg') ?: [] as $path) {
            $name = basename($path);

            if (preg_match('/^(\d+)[-_.]/', $name, $matches)) {
                $icons[(int) $matches[1]] = $this->packages->getUrl(
                    Path::join(self::ICON_ASSET_PATH, $name),
                    Bundle::PACKAGE_NAME,
                );
            }
        }

        ksort($icons);

        return $icons;
    }

    /**
     * Maps the ID of a foreign key table to its title, so the map can resolve
     * the IDs the event API delivers (tour types, organizers).
     *
     * @return array<int, string>
     */
    private function getTitles(string $table): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            \sprintf('SELECT id, title FROM %s ORDER BY title', $table),
        );

        $titles = [];

        /** @var Adapter<StringUtil> $stringUtil */
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        foreach ($rows as $id => $title) {
            $titles[(int) $id] = $stringUtil->revertInputEncoding($title);
        }

        return $titles;
    }

    /**
     * Maps the event type alias (course, tour, lastMinuteTour, generalEvent) to
     * its title, for the labels of the filter.
     *
     * @return array<string, string>
     */
    private function getEventTypeTitles(): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            \sprintf('SELECT alias, title FROM %s ORDER BY title', self::EVENT_TYPE_TABLE),
        );

        $titles = [];

        foreach ($rows as $alias => $title) {
            if ('' !== (string) $alias) {
                $titles[(string) $alias] = html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8');
            }
        }

        return $titles;
    }
}
