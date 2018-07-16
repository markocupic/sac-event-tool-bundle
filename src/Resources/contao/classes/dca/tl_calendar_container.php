<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_calendar_container
 */
class tl_calendar_container extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        // Set correct referer
        if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('ref') != '')
        {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');
            if (isset($session[$ref]['tl_calendar_container']))
            {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar']))
            {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events']))
            {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events_instructor_invoice']))
            {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        parent::__construct();
        $this->import('BackendUser', 'User');
    }


    /**
     * Check permissions to edit table tl_calendar
     *
     * @throws \Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function checkPermission()
    {
        $bundles = System::getContainer()->getParameter('kernel.bundles');

        // HOOK: comments extension required
        if (!isset($bundles['ContaoCommentsBundle']))
        {
            //unset($GLOBALS['TL_DCA']['tl_calendar']['fields']['allowComments']);
        }

        if ($this->User->isAdmin)
        {
            return;
        }

        // Set root IDs
        if (!is_array($this->User->calendar_containers) || empty($this->User->calendar_containers))
        {
            $root = array(0);
        }
        else
        {
            $root = $this->User->calendar_containers;
        }

        $GLOBALS['TL_DCA']['tl_calendar_container']['list']['sorting']['root'] = $root;

        // Check permissions to add calendar_containers
        if (!$this->User->hasAccess('create', 'calendar_containerp'))
        {
            $GLOBALS['TL_DCA']['tl_calendar_container']['config']['closed'] = true;
        }


        /** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
        $objSession = System::getContainer()->get('session');

        // Check current action
        switch (Input::get('act'))
        {
            case 'create':
            case 'select':
                // Allow
                break;

            case 'edit':
                // Dynamically add the record to the user profile
                if (!in_array(Input::get('id'), $root))
                {
                    /** @var Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface $objSessionBag */
                    $objSessionBag = $objSession->getBag('contao_backend');

                    $arrNew = $objSessionBag->get('new_records');

                    if (is_array($arrNew['tl_calendar_container']) && in_array(Input::get('id'), $arrNew['tl_calendar_container']))
                    {
                        // Add the permissions on group level
                        if ($this->User->inherit != 'custom')
                        {
                            $objGroup = $this->Database->execute("SELECT id, calendar_containers, calendar_containerp FROM tl_user_group WHERE id IN(" . implode(',', array_map('intval', $this->User->groups)) . ")");

                            while ($objGroup->next())
                            {
                                $arrCalendarContainerp = StringUtil::deserialize($objGroup->calendar_containerp);

                                if (is_array($arrCalendarContainerp) && in_array('create', $arrCalendarContainerp))
                                {
                                    $arrCalendarContainers = StringUtil::deserialize($objGroup->calendar_containers, true);
                                    $arrCalendarContainers[] = Input::get('id');

                                    $this->Database->prepare("UPDATE tl_user_group SET calendar_containers=? WHERE id=?")
                                        ->execute(serialize($arrCalendarContainers), $objGroup->id);
                                }
                            }
                        }

                        // Add the permissions on user level
                        if ($this->User->inherit != 'group')
                        {
                            $objUser = $this->Database->prepare("SELECT calendar_containers, calendar_containerp FROM tl_user WHERE id=?")
                                ->limit(1)
                                ->execute($this->User->id);

                            $arrCalendarContainerp = StringUtil::deserialize($objGroup->calendar_containerp);

                            if (is_array($arrCalendarContainerp) && in_array('create', $arrCalendarContainerp))
                            {
                                $arrCalendarContainers = StringUtil::deserialize($objGroup->calendar_containers, true);
                                $arrCalendarContainers[] = Input::get('id');

                                $this->Database->prepare("UPDATE tl_user SET calendar_containers=? WHERE id=?")
                                    ->execute(serialize($arrCalendarContainers), $this->User->id);
                            }
                        }

                        // Add the new element to the user object
                        $root[] = Input::get('id');
                        $this->User->calendar_containers = $root;
                    }
                }
            // No break;

            case 'copy':
            case 'delete':
            case 'show':
                if (!in_array(Input::get('id'), $root) || (Input::get('act') == 'delete' && !$this->User->hasAccess('delete', 'calendar_containerp')))
                {
                    throw new \Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' calendar ID ' . Input::get('id') . '.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
                $session = $objSession->all();
                if (Input::get('act') == 'deleteAll' && !$this->User->hasAccess('delete', 'calendar_containerp'))
                {
                    $session['CURRENT']['IDS'] = array();
                }
                else
                {
                    $session['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $root);
                }
                $objSession->replace($session);
                break;

            default:
                if (strlen(Input::get('act')))
                {
                    throw new \Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' calendar_containers.');
                }
                break;
        }
    }


    /**
     * Return the edit header button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        return $this->User->canEditFieldsOf('tl_calendar_container') ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the copy calendar button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function copyCalendarContainer($row, $href, $label, $title, $icon, $attributes)
    {
        return $this->User->hasAccess('create', 'calendar_containerp') ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the delete calendar button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function deleteCalendarContainer($row, $href, $label, $title, $icon, $attributes)
    {
        return $this->User->hasAccess('delete', 'calendar_containerp') ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)) . ' ';
    }
}
