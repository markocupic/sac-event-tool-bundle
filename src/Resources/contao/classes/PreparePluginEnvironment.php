<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Config;
use Contao\Folder;
use Contao\Dbafs;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PreparePluginEnvironment
{
    private $framework;


    /**
     * PreparePluginEnvironment constructor.
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize();
    }


    /**
     * Prepare the plugin environment
     * Create directories
     */
    public function createPluginDirectories()
    {

        // Store all params in $GLOBALS['TL_CONFIG']
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('listener.yml');
        $loader->load('parameters.yml');
        $loader->load('services.yml');

        $parameters = $container->getParameterBag()->all();
        foreach($parameters as $key => $value)
        {
            if(strpos($key, 'SAC_EVT_') !== false)
            {
                if(is_array(json_decode($value)))
                {
                    $value = json_decode($value);
                }
                $GLOBALS['TL_CONFIG'][$key] = $value;
            }
        }

        $dbafs = $this->framework->getAdapter(Dbafs::class);

        // Create FE-USER-HOME-DIR
        $this->framework->createInstance(Folder::class, array(Config::get('SAC_EVT_FE_USER_DIRECTORY_ROOT')));
        $dbafs->addResource(Config::get('SAC_EVT_FE_USER_DIRECTORY_ROOT'));

        // Create FE-USER-AVATAR-DIR
        $this->framework->createInstance(Folder::class, array(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY')));
        $dbafs->addResource(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY'));

        // Create BE-USER-HOME-DIR
        $this->framework->createInstance(Folder::class, array(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT')));
        $dbafs->addResource(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT'));

        // Create tmp folder
        $this->framework->createInstance(Folder::class, array(Config::get('SAC_EVT_TEMP_PATH')));
        $dbafs->addResource(Config::get('SAC_EVT_TEMP_PATH'));

    }

}