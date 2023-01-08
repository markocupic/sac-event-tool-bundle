<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Image;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Message;

class RotateImage
{
    private ContaoFramework $framework;
    private string $projectDir;
    private Adapter $messageAdapter;

    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        $this->messageAdapter = $framework->getAdapter(Message::class);
    }

    /**
     * @throws \ImagickException
     */
    public function rotate(FilesModel $filesModel = null, int $angle = 270, string $target = ''): bool
    {
        if (null === $filesModel) {
            return false;
        }

        $this->framework->initialize();

        if (!file_exists($filesModel->getAbsolutePath())) {
            $this->messageAdapter->addError(sprintf('File "%s" not found.', $filesModel->getAbsolutePath()));

            return false;
        }

        $objFile = new File($filesModel->path);

        if (!$objFile->isGdImage) {
            $this->messageAdapter->addError(sprintf('File "%s" could not be rotated, because it is not an image.', $filesModel->getAbsolutePath()));

            return false;
        }

        if ('' === $target) {
            $target = $filesModel->getAbsolutePath();
        } else {
            new Folder(\dirname($target));
            $target = $this->projectDir.'/'.$target;
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel')) {
            $imagick = new \Imagick();

            if ($imagick->readImage($filesModel->getAbsolutePath())) {
                if ($imagick->rotateImage(new \ImagickPixel('none'), $angle)) {
                    if ($imagick->writeImage($target)) {
                        $imagick->clear();
                        $imagick->destroy();

                        return true;
                    }
                }
            }

            $this->messageAdapter->addError(sprintf('Please install class "%s" or php function "%s" for rotating images.', 'Imagick', 'imagerotate'));

            return false;
        }

        if (\function_exists('imagerotate')) {
            $objGdImage = imagecreatefromjpeg($filesModel->getAbsolutePath());

            if (false !== $objGdImage) {
                $objRotGdImage = imagerotate($objGdImage, $angle, 0);

                if (imagejpeg($objRotGdImage, $target)) {
                    // Free the memory
                    imagedestroy($objGdImage);
                    imagedestroy($objRotGdImage);

                    if (is_file($target)) {
                        return true;
                    }
                }

                imagedestroy($objGdImage);
            }

            $this->messageAdapter->addError('An unexpected error occurred while attempting to rotate the image.');

            return false;
        }

        $this->messageAdapter->addError('Could not find any PHP library to rotate the image.');

        return false;
    }
}
