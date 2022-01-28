<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolEventPreviewReader.
 */
class ModuleSacEventToolEventPreviewReader extends Events
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_eventreader';

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewReader'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('events', Input::get('auto_item'));
        }

        /** @var PageModel $objPage */
        global $objPage;

        $objPage->noSearch = 1;
        $objPage->cache = 0;

        $blnShow = false;

        if ('' !== Input::get('events')) {
            $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));

            if (null !== $objEvent) {
                if ($objEvent->eventToken === $_GET['eventToken']) {
                    $blnShow = true;
                }
            }
        }

        if (!$blnShow) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        /** @var PageModel $objPage */
        global $objPage;

        $this->Template->event = '';
        $this->Template->referer = 'javascript:history.go(-1)';
        $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];

        // Get the current event
        $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));

        if (null === $objEvent) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // Overwrite the page title (see #2853 and #4955)
        if ('' !== $objEvent->title) {
            $objPage->pageTitle = strip_tags(StringUtil::stripInsertTags($objEvent->title));
        }

        // Overwrite the page description
        if ('' !== $objEvent->teaser) {
            $objPage->description = $this->prepareMetaDescription($objEvent->teaser);
        }

        $intStartTime = $objEvent->startTime;
        $intEndTime = $objEvent->endTime;
        $span = Calendar::calculateSpan($intStartTime, $intEndTime);

        // Do not show dates in the past if the event is recurring (see #923)
        if ($objEvent->recurring) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach);

            if (\is_array($arrRange) && isset($arrRange['unit'], $arrRange['value'])) {
                while ($intStartTime < time() && $intEndTime < $objEvent->repeatEnd) {
                    $intStartTime = strtotime('+'.$arrRange['value'].' '.$arrRange['unit'], $intStartTime);
                    $intEndTime = strtotime('+'.$arrRange['value'].' '.$arrRange['unit'], $intEndTime);
                }
            }
        }

        $strDate = Date::parse($objPage->dateFormat, $intStartTime);

        if ($span > 0) {
            $strDate = Date::parse($objPage->dateFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->dateFormat, $intEndTime);
        }

        $strTime = '';

        if ($objEvent->addTime) {
            if ($span > 0) {
                $strDate = Date::parse($objPage->datimFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->datimFormat, $intEndTime);
            } elseif ((int) $intStartTime === (int) $intEndTime) {
                $strTime = Date::parse($objPage->timeFormat, $intStartTime);
            } else {
                $strTime = Date::parse($objPage->timeFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->timeFormat, $intEndTime);
            }
        }

        $until = '';
        $recurring = '';

        // Recurring event
        if ($objEvent->recurring) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach);

            if (\is_array($arrRange) && isset($arrRange['unit'], $arrRange['value'])) {
                $strKey = 'cal_'.$arrRange['unit'];
                $recurring = sprintf($GLOBALS['TL_LANG']['MSC'][$strKey], $arrRange['value']);

                if ($objEvent->recurrences > 0) {
                    $until = sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }
            }
        }

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_template);
        $objTemplate->setData($objEvent->row());

        $objTemplate->date = $strDate;
        $objTemplate->time = $strTime;
        $objTemplate->datetime = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);
        $objTemplate->begin = $intStartTime;
        $objTemplate->end = $intEndTime;
        $objTemplate->class = '' !== $objEvent->cssClass ? ' '.$objEvent->cssClass : '';
        $objTemplate->recurring = $recurring;
        $objTemplate->until = $until;
        $objTemplate->locationLabel = $GLOBALS['TL_LANG']['MSC']['location'];
        $objTemplate->details = '';
        $objTemplate->hasDetails = false;
        $objTemplate->hasTeaser = false;

        // Clean the RTE output
        if ('' !== $objEvent->teaser) {
            $objTemplate->hasTeaser = true;
            $objTemplate->teaser = StringUtil::toHtml5($objEvent->teaser);
            $objTemplate->teaser = StringUtil::encodeEmail($objTemplate->teaser);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objEvent->source) {
            $objTemplate->details = true;
            $objTemplate->hasDetails = true;
        }

        // Compile the event text
        else {
            $id = $objEvent->id;

            $objTemplate->details = function () use ($id) {
                $strDetails = '';
                $objElement = ContentModel::findPublishedByPidAndTable($id, 'tl_calendar_events');

                if (null !== $objElement) {
                    while ($objElement->next()) {
                        $strDetails .= $this->getContentElement($objElement->current());
                    }
                }

                return $strDetails;
            };

            $objTemplate->hasDetails = static fn () => ContentModel::countPublishedByPidAndTable($id, 'tl_calendar_events') > 0;
        }

        $objTemplate->addImage = false;

        // Add an image
        if ($objEvent->addImage && '' !== $objEvent->singleSRC) {
            $objModel = FilesModel::findByUuid($objEvent->singleSRC);

            if (null !== $objModel && is_file(TL_ROOT.'/'.$objModel->path)) {
                // Do not override the field now that we have a model registry (see #6303)
                $arrEvent = $objEvent->row();

                // Override the default image size
                if ('' !== $this->imgSize) {
                    $size = StringUtil::deserialize($this->imgSize);

                    if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2])) {
                        $arrEvent['size'] = $this->imgSize;
                    }
                }

                $arrEvent['singleSRC'] = $objModel->path;
                $this->addImageToTemplate($objTemplate, $arrEvent, null, null, $objModel);
            }
        }

        $objTemplate->enclosure = [];

        // Add enclosures
        if ($objEvent->addEnclosure) {
            $this->addEnclosuresToTemplate($objTemplate, $objEvent->row());
        }

        $this->Template->event = $objTemplate->parse();

        $bundles = System::getContainer()->getParameter('kernel.bundles');

        // HOOK: comments extension required
        if ($objEvent->noComments || !isset($bundles['ContaoCommentsBundle'])) {
            $this->Template->allowComments = false;

            return;
        }

        /** @var CalendarModel $objCalendar */
        $objCalendar = $objEvent->getRelated('pid');
        $this->Template->allowComments = $objCalendar->allowComments;

        // Comments are not allowed
        if (!$objCalendar->allowComments) {
            return;
        }

        // Adjust the comments headline level
        $intHl = min((int) str_replace('h', '', $this->hl), 5);
        $this->Template->hlc = 'h'.($intHl + 1);

        $this->import('Comments');
        $arrNotifies = [];

        // Notify the system administrator
        if ('notify_author' !== $objCalendar->notify) {
            $arrNotifies[] = $GLOBALS['TL_ADMIN_EMAIL'];
        }

        // Notify the author
        if ('notify_admin' !== $objCalendar->notify) {
            /** @var UserModel $objAuthor */
            if (($objAuthor = $objEvent->getRelated('author')) instanceof UserModel && '' !== $objAuthor->email) {
                $arrNotifies[] = $objAuthor->email;
            }
        }

        $objConfig = new \stdClass();

        $objConfig->perPage = $objCalendar->perPage;
        $objConfig->order = $objCalendar->sortOrder;
        $objConfig->template = $this->com_template;
        $objConfig->requireLogin = $objCalendar->requireLogin;
        $objConfig->disableCaptcha = $objCalendar->disableCaptcha;
        $objConfig->bbcode = $objCalendar->bbcode;
        $objConfig->moderate = $objCalendar->moderate;

        $this->Comments->addCommentsToTemplate($this->Template, $objConfig, 'tl_calendar_events', $objEvent->id, $arrNotifies);
    }
}
