<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\FilesModel;
use Markocupic\ContaoFrontendUserNotification\Event\DeleteOutdatedFrontendUserNotificationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsEventListener]
final readonly class DeleteOutdatedFrontendUserNotificationListener
{
    public function __invoke(DeleteOutdatedFrontendUserNotificationEvent $event): void
    {
        $model = $event->getModel();

        if (empty($model->tourlistSRC)) {
            return;
        }

        $filesModel = FilesModel::findByUuid($model->tourlistSRC);

        if (null !== $filesModel) {
            $pathParts = pathinfo($filesModel->getAbsolutePath());

            // Delete both: docx and pdf
            $finder = new Finder();
            $finder
                ->in($pathParts['dirname'])
                ->files()
                ->name($pathParts['filename'].'.*')
            ;

            $fs = new Filesystem();

            $fs->remove($finder->getIterator());
        }
    }
}
