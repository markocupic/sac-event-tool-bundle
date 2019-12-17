<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Services\Image;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Message;

/**
 * Class RotateImage
 * @package Markocupic\SacEventToolBundle\Services\Image
 */
class RotateImage
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * RotateImage constructor.
     * @param ContaoFramework $framework
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
    }

    /**
     * @param FilesModel|null $objFiles
     * @param int $angle
     * @param string $target
     * @return bool
     * @throws \ImagickException
     */
    public function rotate(FilesModel $objFiles = null, int $angle = 270, string $target = ''): bool
    {
        if ($objFiles === null)
        {
            return false;
        }

        // Initialize contao framework
        $this->framework->initialize();

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $src = $objFiles->path;

        if (!file_exists($this->projectDir . '/' . $src))
        {
            $messageAdapter->addError(sprintf('File "%s" not found.', $src));
            return false;
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            $messageAdapter->addError(sprintf('File "%s" could not be rotated, because it is not an image.', $src));
            return false;
        }

        $source = $this->projectDir . '/' . $src;
        if ($target === '')
        {
            $target = $source;
        }
        else
        {
            new Folder(dirname($target));
            $target = $this->projectDir . '/' . $target;
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel'))
        {
            $imagick = new \Imagick();

            $imagick->readImage($source);
            $imagick->rotateImage(new \ImagickPixel('none'), $angle);
            $imagick->writeImage($target);
            $imagick->clear();
            $imagick->destroy();
            return true;
        }
        elseif (function_exists('imagerotate'))
        {
            $source = imagecreatefromjpeg($this->projectDir . '/' . $src);

            //rotate
            $imgTmp = imagerotate($source, $angle, 0);

            // Output
            imagejpeg($imgTmp, $target);

            imagedestroy($source);
            if (is_file($target))
            {
                return true;
            }
        }
        else
        {
            $messageAdapter->addError(sprintf('Please install class "%s" or php function "%s" for rotating images.', 'Imagick', 'imagerotate'));
            return false;
        }
        return false;
    }
}
