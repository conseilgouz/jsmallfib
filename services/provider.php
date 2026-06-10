<?php

/**
 * @package Plugin JSmallfib for Joomla! 5.x/6.x
 * @version 5.0
 * @author ConseilGouz
 * @ from Enrico Sandoli
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved
 * @license GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 **/

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Lomart\Plugin\Content\JSmallFib\Extension\JSmallFib;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.3.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
				$dispatcher = $container->get(DispatcherInterface::class);
				$plugin     = new JSmallFib(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('content', 'jsmallfib')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
