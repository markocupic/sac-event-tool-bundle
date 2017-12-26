<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

declare(strict_types=1);

namespace PhpOffice\PhpWord;

use Contao\Folder;
use Contao\File;
use Contao\System;


class TemplateProcessorExtended extends TemplateProcessor
{

    /**
     * Replace template vars and create docx-file from docx-template while
     * using the PhpOffice\PhpWord\TemplateProcessor class
     *
     * $arrData = array();
     *
     * // Simple replacement
     * $arrData[] = array('key' => 'regId', 'value' => $objRegistration->id);
     *
     * // Clone rows
     * $arrData[] = array(
     *     'clone' => 'cloneA',
     *     'rows' => array
     *     (
     *         // Row 1
     *         array(
     *             array('key' => 'cloneA', 'value' => 'someValueA', 'options' => array('multiline' => true)),
     *             array('key' => 'cloneB', 'value' => 'someValueB', 'options' => array('multiline' => true)),
     *             array('key' => 'cloneC', 'value' => 'someValueC', 'options' => array('multiline' => true))
     *         ),
     *         // Row 2
     *         array(
     *             array('key' => 'cloneA', 'value' => 'someValueA', 'options' => array('multiline' => true)),
     *             array('key' => 'cloneB', 'value' => 'someValueB', 'options' => array('multiline' => true)),
     *             array('key' => 'cloneC', 'value' => 'someValueC', 'options' => array('multiline' => true))
     *         )
     *     )
     * );
     * // Create docx file from template and send it to the browser
     * PhpOffice\PhpWord\TemplateProcessorExtended::create($arrData, 'files/template.docx', 'files/tmp', 'mydocxfile.docx', true);
     *
     *
     * @param $arrData
     * @param $templSRC
     * @param $tempSRC
     * @param $filename
     * @param bool $sendToBrowser
     * @param bool $blnUncached
     */
    public static function create($arrData, $templSRC, $tempSRC, $filename, $sendToBrowser = false, $blnUncached=false): void
    {

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Create docx file if it can not bee found in the cache or if $blnUncached is set to true
        if (!is_file($rootDir . '/' . $tempSRC . '/' . $filename) || $blnUncached === true)
        {
            // Instantiate the Template processor
            $templateProcessor = new TemplateProcessorExtended($rootDir . '/' . $templSRC);

            // Process $arrData and replace the template vars
            foreach ($arrData as $arrData)
            {
                if (isset($arrData['clone']) && !empty($arrData['clone']))
                {
                    // Clone rows
                    if (count($arrData['rows']) > 0)
                    {
                        $templateProcessor->cloneRow($arrData['clone'], count($arrData['rows']));

                        $row = 0;
                        foreach ($arrData['rows'] as $key => $arrRow)
                        {
                            $row = $key + 1;
                            foreach ($arrRow as $arrRowData)
                            {
                                $templateProcessor->setValue($arrRowData['key'] . '#' . $row, $arrRowData['value']);
                            }
                        }
                    }
                }
                else
                {
                    $templateProcessor->setValue($arrData['key'], $arrData['value']);
                }
            }

            // Create temp directory
            new Folder($tempSRC);

            $templateProcessor->saveAs($rootDir . '/' . $tempSRC . '/' . $filename);
        }

        $objDocx = new File($tempSRC . '/' . $filename);
        sleep(1);

        if ($sendToBrowser)
        {
            $objDocx->sendToBrowser();
        }
    }

    /**
     * @param $text
     * @return mixed|string
     */
    protected static function formatMultilineText($text)
    {
        $text = htmlspecialchars(html_entity_decode($text));
        $text = preg_replace('~\R~u', '</w:t><w:br/><w:t>', $text);
        return $text;
    }

    /**
     * Replace a block.
     *
     * @param string $blockname
     * @param string $replacement
     *
     * @return void
     */
    public function replaceBlock($blockname, $replacement)
    {
        // Original pattern
        // '/(<\?xml.*)(<w:p.*>\${' . $blockname . '}<\/w:.*?p>)(.*)(<w:p.*\${\/' . $blockname . '}<\/w:.*?p>)/is',
        // Optimized pattern for Word 2017
        preg_match(

            '/(<\?xml.*)(<w:t.*>\${' . $blockname . '}<\/w:.*?t>)(.*)(<w:t.*\${\/' . $blockname . '}<\/w:.*?t>)/is',
            $this->tempDocumentMainPart,
            $matches
        );

        if (isset($matches[3]))
        {
            $this->tempDocumentMainPart = str_replace(
                $matches[2] . $matches[3] . $matches[4],
                $replacement,
                $this->tempDocumentMainPart
            );
        }
    }
}
