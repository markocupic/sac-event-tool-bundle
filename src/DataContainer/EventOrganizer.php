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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class EventOrganizer
{
    private Connection $connection;


    public function __construct( Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Callback(table="tl_event_organizer", target="fields.belongsToOrganization.options")
     */
    public function getSacSections(): array
    {
        $arrOptions = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_sac_section',[]);

        while (false !== ($arrSection = $stmt->fetchAssociative()))
        {
            $arrOptions[$arrSection['sectionId']] = $arrSection['name'];
        }

        return $arrOptions;
    }
}