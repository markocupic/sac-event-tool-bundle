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

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MaintainBackendUserProperties
{
    private ContaoFramework $framework;
    private Connection $connection;

    public function __construct(ContaoFramework $framework, Connection $connection)
    {
        $this->framework = $framework;
        $this->connection = $connection;
    }

    /**
     * @throws Exception
     */
    public function clearBackendUserRights(string $strUserIdentifier, array $arrSkip = []): void
    {
        // Initialize contao framework
        $this->framework->initialize();

        $arrUserProps = $this->connection->fetchAssociative('SELECT * FROM tl_user WHERE username = ?', [$strUserIdentifier]);

        if (false !== $arrUserProps) {
            // Contao core permissions
            $arrPerm = ['alexf', 'modules', 'themes', 'elements', 'fields', 'pagemounts', 'alpty', 'filemounts', 'fop', 'forms', 'formp', 'imageSizes', 'amg'];

            // Custom permissions like: faqs,faqp,news,newp,newsfeeds,newsfeedp,calendars,calendarp,calendarfeeds,calendarfeedp,newsletters,newsletterp,calendar_containers,calendar_containerp
            if (!empty($GLOBALS['TL_PERMISSIONS']) && \is_array($GLOBALS['TL_PERMISSIONS'])) {
                $arrPerm = array_merge($arrPerm, $GLOBALS['TL_PERMISSIONS']);
            }

            $arrPerm = array_diff($arrPerm, $arrSkip);
            $arrPerm = array_unique($arrPerm);

            if (!empty($arrPerm)) {
                $set = [];

                foreach ($arrPerm as $perm) {
                    if (isset($arrUserProps[$perm])) {
                        $set[$perm] = serialize([]);
                    }
                }

                if (!empty($set)) {
                    $set['tstamp'] = time();

                    if ('andrewiederhold' === $strUserIdentifier) {
                        $this->connection->update('tl_user', $set, ['username' => $strUserIdentifier]);
                    }
                }
            }
        }
    }
}
