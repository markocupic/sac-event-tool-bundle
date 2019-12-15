<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Message;
use Contao\System;

/**
 * Class RotateImage
 * @package Markocupic\SacEventToolBundle
 */
class RotateImage
{

    /**
     * @param $fileId
     * @param int $angle
     * @param string $target
     * @return bool
     * @throws \ImagickException
     */
    public static function rotate($fileId, int $angle = 270, string $target = ''): bool
    {
        if (!is_numeric($fileId) && $fileId < 1)
        {
            return false;
        }

        $objFiles = FilesModel::findById($fileId);
        if ($objFiles === null)
        {
            return false;
        }

        $src = $objFiles->path;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists($rootDir . '/' . $src))
        {
            Message::addError(sprintf('File "%s" not found.', $src));
            return false;
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));
            return false;
        }

        $source = $rootDir . '/' . $src;
        if ($target === '')
        {
            $target = $source;
        }
        else
        {
            new Folder(dirname($target));
            $target = $rootDir . '/' . $target;
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
            $source = imagecreatefromjpeg($rootDir . '/' . $src);

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
            Message::addError(sprintf('Please install class "%s" or php function "%s" for rotating images.', 'Imagick', 'imagerotate'));
            return false;
        }
        return false;
    }
}
