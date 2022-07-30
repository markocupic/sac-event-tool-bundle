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

namespace Markocupic\SacEventToolBundle\Subscriber;

use Contao\File;
use Doctrine\DBAL\Connection;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\PreInteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PreSingleSignOnLoginSubscriber implements EventSubscriberInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreInteractiveLoginEvent::NAME => ['onPreSingleSignOnLogin', 100],
        ];
    }

    public function onPreSingleSignOnLogin(PreInteractiveLoginEvent $event): void
    {
        $username = $event->getUserIdentifier();
        //$resOwner = $event->getResourceOwner();
        $strUserClass = $event->getUserClass();
        //$contaoUserProvider = $event->getUserProvider();

        // Do some cool stuff with the nearly logged-in user
        $file = new File('files/login.txt');
        $file->append(sprintf('%s %s %s', date('Y-m-d H:i:s', time()), $strUserClass, $username));
        $file->close();
    }
}
