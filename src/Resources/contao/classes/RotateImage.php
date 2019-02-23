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
     * @return bool
     * @throws \Exception
     */
    public static function rotate($fileId, $angle = 270)
    {
        if(!is_numeric($fileId) && $fileId < 1)
        {
            return false;
        }
        $angle = 270;
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

        if (!function_exists('imagerotate'))
        {
            Message::addError(sprintf('PHP function "%s" is not installed.', 'imagerotate'));
            return false;
        }

        $source = imagecreatefromjpeg($rootDir . '/' . $src);

        //rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $rootDir . '/' . $src);

        imagedestroy($source);
        return true;
    }
}