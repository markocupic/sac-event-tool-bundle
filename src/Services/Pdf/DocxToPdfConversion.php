<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\Pdf;

use CloudConvert\Api;
use CloudConvert\Exceptions\ApiBadRequestException;
use CloudConvert\Exceptions\ApiConversionFailedException;
use CloudConvert\Exceptions\ApiTemporaryUnavailableException;
use Contao\Folder;
use Contao\File;
use Contao\System;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Converts docx to pdf using the Cloudconvert Api
 *
 * Class DocxToPdfConversion
 * @package Markocupic\SacEventToolBundle\Services\Pdf
 */
class DocxToPdfConversion
{

    /**
     * @var
     */
    private $apiKey;

    /**
     * @var
     */
    private $docxSrc;

    /**
     * @var bool
     */
    private $sendToBrowser = false;

    /**
     * @var bool
     */
    private $createUncached = false;

    /**
     * Creates a new object instance.
     *
     * @param string $docxSrc
     * @param string $apiKey
     * @return static
     */
    public static function create(string $docxSrc, string $apiKey)
    {
        $rootDir = dirname(__DIR__ . '/../../../../../../../');
        if (!file_exists($rootDir . '/' . $docxSrc))
        {
            throw new FileNotFoundException(sprintf('Docx file "%s" not found. Conversion aborted.', $docxSrc));
        }

        $objConv = new static();
        $objConv->docxSrc = $docxSrc;
        $objConv->apiKey = $apiKey;
        return $objConv;
    }

    /**
     * @param bool $blnSendToBrowser
     * @return static
     */
    public function sendToBrowser($blnSendToBrowser = false)
    {
        $this->sendToBrowser = $blnSendToBrowser;
        return $this;
    }

    /**
     * @param bool $blnUncached
     * @return static
     */
    public function createUncached($blnUncached = false)
    {
        $this->createUncached = $blnUncached;
        return $this;
    }

    /**
     * @return string
     */
    public function bla()
    {
        new \Contao\Folder('files/bla');
        return 'foo';
    }

    /**
     *
     */
    public function convert(): void
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $pathParts = pathinfo($this->docxSrc);
        $tmpDir = $pathParts['dirname'];
        $filename = $pathParts['filename'];
        $pdfSRC = $tmpDir . '/' . $filename . '.pdf';

        // Convert docx file to pdf if it can not bee found in the cache
        if (!is_file($rootDir . '/' . $pdfSRC) || $this->createUncached === true)
        {
            // Be sure the folder exists
            new Folder($tmpDir);

            $api = new Api($this->apiKey);
            try
            {
                // https://cloudconvert.com/api/console
                $api->convert([
                    'inputformat'  => 'docx',
                    'outputformat' => 'pdf',
                    'input'        => 'upload',
                    'file'         => fopen($rootDir . '/' . $this->docxSrc, 'r'),
                ])->wait()->download($rootDir . '/' . $pdfSRC);
            } // Exception handling
            catch (ApiBadRequestException $e)
            {
                echo "Something with your request is wrong: " . $e->getMessage();
            } catch (ApiConversionFailedException $e)
            {
                echo "Conversion failed, maybe because of a broken input file: " . $e->getMessage();
            } catch (ApiTemporaryUnavailableException $e)
            {
                echo "API temporary unavailable: " . $e->getMessage() . "\n";
                echo "We should retry the conversion in " . $e->retryAfter . " seconds";
            } catch (\Exception $e)
            {
                // Network problems, etc..
                echo "Something else went wrong: " . $e->getMessage() . "\n";
            }
        }

        if ($this->sendToBrowser)
        {
            // Send converted file to the browser
            sleep(1);
            $objPdf = new File($pdfSRC);
            $objPdf->sendToBrowser();
        }
    }
}
