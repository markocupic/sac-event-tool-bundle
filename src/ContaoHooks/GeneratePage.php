<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Automator;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Psr\Log\LogLevel;

/**
 * Class GeneratePage
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GeneratePage
{
    /**
     * @var ContaoFramework
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }


    /**
     *
     */
    public function generatePage()
    {

        $disabled = true;
        if (!$disabled && Input::get('action') === 'importNewsletterRecipients')
        {
            Database::getInstance()->prepare('DELETE FROM tl_newsletter_recipients')->execute();

            $newsletterChannelId = 6;
            $time = time();
            $addedOn = time();
            $objDb = Database::getInstance()->execute('SELECT * FROM tl_newsletter_jugend');
            $sets = array();
            $arrEmail = [];
            while ($objDb->next())
            {
                $token = md5(uniqid(mt_rand(), true));
                $objMember = MemberModel::findByEmail($objDb->email);
                if ($objMember !== null)
                {
                    $objMember->newsletter = serialize([$newsletterChannelId]);
                    $objMember->save();
                    $sets[strtolower($objMember->email)] = array(
                        'pid'     => $newsletterChannelId,
                        'tstamp'  => $time,
                        'email'   => $objMember->email,
                        'active'  => '1',
                        'addedOn' => $addedOn,
                        'token'   => $token
                    );
                }
                else
                {
                    if ($objDb->email != '')
                    {
                        $sets[strtolower($objDb->email)] = array(
                            'pid'     => $newsletterChannelId,
                            'tstamp'  => $time,
                            'email'   => $objDb->email,
                            'active'  => '1',
                            'addedOn' => $addedOn,
                            'token'   => $token
                        );

                    }
                }
            }

            // Start transaction
            Database::getInstance()->beginTransaction();
            try
            {
                foreach ($sets as $set)
                {
                    Database::getInstance()->prepare('INSERT INTO tl_newsletter_recipients %s')->set($set)->execute();
                }
                Database::getInstance()->commitTransaction();
            } catch (\Exception $e)
            {

                //transaction rollback
                Database::getInstance()->rollbackTransaction();
                throw $e;
            }
        }
    }

}


