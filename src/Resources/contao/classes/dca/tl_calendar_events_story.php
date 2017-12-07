<?php

/**
 * Class tl_calendar_events_story
 */
class tl_calendar_events_story extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');


    }

    /**
     *
     * OnLoad Callback
     */
    public function onloadCallback()
    {

    }

    /**
     * @param $strContent
     * @param $strTemplate
     * @return mixed
     */
    public function parseBackendTemplate($strContent, $strTemplate)
    {

    }


    /**
     * Add an image to each record
     * @param array $row
     * @param string $label
     * @param DataContainer $dc
     * @param array $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
    {
        $image = 'member';
        $disabled = false;
        if ($row['publishState'] != '3')
        {
            $image .= '_';
            $disabled = true;
        }

        $args[0] = sprintf('<div class="list_icon_new" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="%s.svg" data-icon-disabled="%s.svg">&nbsp;</div>', TL_ASSETS_URL, Backend::getTheme(), $image, $disabled ? $image : rtrim($image, '_'), rtrim($image, '_') . '_');

        return $args;
    }


    /**
     * Return the "toggle visibility" button
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
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }


        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
            // Full access to admins
        }
        elseif (array_intersect(StringUtil::deserialize($this->User->groups, true), array(SAC_EVT_GRUPPE_EVENTERFASSUNG_HAUPTREDAKTOREN)))
        {
            // If user belongs to group "Hauptredaktor" grant full rights.
        }
        else
        {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    return '';
                }
            }
        }


        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . $row['disable'];

        if ($row['disable'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"') . '</a> ';
    }


    /**
     * Disable/enable a registration
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }


        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
        }
        elseif (array_intersect(StringUtil::deserialize($this->User->groups, true), array(SAC_EVT_GRUPPE_EVENTERFASSUNG_HAUPTREDAKTOREN)))
        {
            // If user belongs to group "Hauptredaktor" grant full rights.
        }
        else
        {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate registration ID ' . $id . '.');
                }
            }
        }


        $objVersions = new Versions('tl_calendar_events_story', $intId);
        $objVersions->initialize();

        // Reverse the logic (members have disabled=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_calendar_events_story']['fields']['disable']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events_story']['fields']['disable']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_calendar_events_story SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        $objVersions->create();


    }
}
