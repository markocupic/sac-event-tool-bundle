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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

#[AsHook('replaceInsertTags', priority: 100)]
class ReplaceInsertTagsListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $strTag): bool|string
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Trim whitespaces
        $strTag = '' !== $strTag ? trim($strTag) : $strTag;

        // Replace external link
        // {{external_link::http://google.ch::more}}
        if (str_contains($strTag, 'external_link')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $href = $elements[1];
                $label = $href;

                if (isset($elements[2]) && '' !== $elements[2]) {
                    $label = $elements[2];
                }

                return sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', $href, $label);
            }
        }

        // Redirect to an internal page
        // {{redirect::###pageIdOrAlias###::###params###}}
        // {{redirect::konto-aktivieren}}
        // {{redirect::some-page-alias::?foo=bar&var=bla}}
        if (str_contains($strTag, 'redirect')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $params = '';

                if (isset($elements[2])) {
                    $params = $elements[2];
                }
                $objPage = $pageModelAdapter->findByIdOrAlias($elements[1]);

                if (null !== $objPage) {
                    $strLocation = sprintf('%s%s', $objPage->getFrontendUrl(), $params);
                    $controllerAdapter->redirect($strLocation);
                }
            }
        }

        // {{count_frontend_user}} -> 86
        // {{count_frontend_user::10}} -> 80 floor value
        if (str_starts_with($strTag, 'count_frontend_users')) {
            $floor = (int) ($elements[1] ?? 1);
            $count = $this->connection->fetchOne('SELECT COUNT(id) FROM tl_member WHERE isSacMember = ?', [true], [Types::BOOLEAN]);
            return (string) (floor($count/$floor) * $floor);
        }

        // {{count_by_event_type::tour}} -> 764
        // {{count_by_event_type::tour::100}} -> 700 floor value
        if (str_starts_with($strTag, 'count_by_event_type')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $eventType = $elements[1];
                $floor = (int) ($elements[2] ?? 1);

                // Get the current year
                $currentYear = (int) date("Y");
                $nextYear = $currentYear + 1;

                // Create a timestamp for January 1st of the current year
                $tstampStart = mktime(0, 0, 0, 1, 1, $currentYear);
                $tstampEnd = mktime(0, 0, 0, 1, 1, $nextYear);

                $count =  $this->connection->fetchOne(
                    'SELECT COUNT(id) FROM tl_calendar_events WHERE eventType = ? AND published = ? AND startDate >= ? AND startDate < ?',
                    [$eventType, true, $tstampStart, $tstampEnd],
                    [Types::STRING, Types::BOOLEAN, Types::INTEGER, Types::INTEGER],
                );

                return (string) (floor($count/$floor) * $floor);
            }
        }

        return false;
    }
}
