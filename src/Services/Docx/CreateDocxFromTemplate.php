<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

declare(strict_types=1);

namespace PhpOffice\PhpWord;

use Contao\Folder;
use Contao\File;
use Contao\System;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;



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
 * PhpOffice\PhpWord\CreateDocxFromTemplate::create($arrData, 'files/my_docx_template.docx', 'files/tmp/my_docx_file.docx')
 * ->sendToBrowser(true)
 * ->generateUncached(true);
 *
 */
class CreateDocxFromTemplate extends TemplateProcessor
{

    /**
     * @var
     */
    private $arrData;

    /**
     * @var
     */
    private $templSrc;

    /**
     * @var
     */
    private $targetSrc;

    /**
     * @var bool
     */
    private $sendToBrowser = false;


    /**
     * @var bool
     */
    private $generateUncached = false;

    /**
     * @var
     */
    private $rootDir;


    /**
     * @param array $arrData
     * @param string $templSrc
     * @param string $targetSrc
     * @return static
     */
    public static function create(array $arrData, string $templSrc, string $targetSrc)
    {
        $rootDir = dirname(__DIR__ . '/../../../../../../../');
        if (!file_exists($rootDir . '/' . $templSrc))
        {
            throw new FileNotFoundException(sprintf('Template file "%s" not found.', $templSrc));
        }

        $self = new static($rootDir . '/' . $templSrc);
        $self->rootDir = $rootDir;
        $self->arrData = $arrData;
        $self->templSrc = $templSrc;
        $self->targetSrc = $targetSrc;
        return $self;
    }

    /**
     * @param bool $blnSendToBrowser
     * @return static
     */
    public function sendToBrowser($blnSendToBrowser = false): self
    {
        $this->sendToBrowser = $blnSendToBrowser;
        return $this;
    }


    /**
     * @param bool $blnUncached
     * @return static
     */
    public function generateUncached($blnUncached = false): self
    {
        $this->generateUncached = $blnUncached;
        return $this;
    }


    /**
     *
     */
    public function generate(): void
    {
        // Create docx file if it can not be found in the cache or if $this->generateUncached is set to true
        if (!is_file($this->rootDir . '/' . $this->targetSrc) || $this->generateUncached === true)
        {
            // Process $this->arrData and replace the template vars
            foreach ($this->arrData as $aData)
            {
                if (isset($aData['clone']) && !empty($aData['clone']))
                {
                    // Clone rows
                    if (count($aData['rows']) > 0)
                    {
                        $this->cloneRow($aData['clone'], count($aData['rows']));

                        $row = 0;
                        foreach ($aData['rows'] as $key => $arrRow)
                        {
                            $row = $key + 1;
                            foreach ($arrRow as $arrRowData)
                            {
                                $this->setValue($arrRowData['key'] . '#' . $row, $arrRowData['value']);
                            }
                        }
                    }
                }
                else
                {
                    $this->setValue($aData['key'], $aData['value']);
                }
            }

            $this->saveAs($this->rootDir . '/' . $this->targetSrc);
        }


        if ($this->sendToBrowser)
        {
            $objDocx = new File($this->targetSrc);
            $objDocx->sendToBrowser();
        }
    }


    /**
     * @param $text
     * @return mixed|string
     */
    protected static function formatMultilineText($text): string
    {
        $text = htmlspecialchars(html_entity_decode($text));
        $text = preg_replace('~\R~u', '</w:t><w:br/><w:t>', $text);
        return $text;
    }


    /**
     * Replace a block.
     * Overwrite original method
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
