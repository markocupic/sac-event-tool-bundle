<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\Pdf;

use CloudConvert\Api;
use CloudConvert\Exceptions\ApiBadRequestException;
use CloudConvert\Exceptions\ApiConversionFailedException;
use CloudConvert\Exceptions\ApiTemporaryUnavailableException;
use Contao\Folder;
use Contao\File;
use Contao\System;

/**
 * Class DocxToPdfConversion
 * @package Markocupic\SacEventToolBundle\Services\Pdf
 */
class DocxToPdfConversion
{

    /**
     * @param $docxSRC
     * @param $apiKey
     * @param bool $sendToBrowser
     * @param bool $blnUncached
     */
    static function convert($docxSRC, $apiKey, $sendToBrowser = false, $blnUncached = false)
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $pathParts = pathinfo($docxSRC);
        $tmpDir = $pathParts['dirname'];
        $filename = $pathParts['filename'];
        $pdfSRC = $tmpDir . '/' . $filename . '.pdf';

        // Convert docx file to pdf if it can not bee found in the cache
        if (!is_file($rootDir . '/' . $pdfSRC || $blnUncached === true))
        {
            // Be sure the folder exists
            new Folder($tmpDir);

            $api = new Api($apiKey);
            try
            {
                // https://cloudconvert.com/api/console
                $api->convert([
                    'inputformat' => 'docx',
                    'outputformat' => 'pdf',
                    'input' => 'upload',
                    'file' => fopen($rootDir . '/' . $docxSRC, 'r'),
                ])->wait()->download($rootDir . '/' . $pdfSRC);
            }
                // Exception handling
            catch (ApiBadRequestException $e)
            {
                echo "Something with your request is wrong: " . $e->getMessage();
            }
            catch (ApiConversionFailedException $e)
            {
                echo "Conversion failed, maybe because of a broken input file: " . $e->getMessage();
            }
            catch (ApiTemporaryUnavailableException $e)
            {
                echo "API temporary unavailable: " . $e->getMessage() . "\n";
                echo "We should retry the conversion in " . $e->retryAfter . " seconds";
            }
            catch (\Exception $e)
            {
                // Network problems, etc..
                echo "Something else went wrong: " . $e->getMessage() . "\n";
            }
        }

        if ($sendToBrowser)
        {
            // Send converted file to the browser
            sleep(1);
            $objPdf = new File($pdfSRC);
            $objPdf->sendToBrowser();
        }
    }
}
