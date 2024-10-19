<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Tests\ContaoManager;

use Code4Nix\UriSigner\Code4NixUriSigner;
use Contao\CalendarBundle\ContaoCalendarBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\DelegatingParser;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ContaoFrontendUserNotification\MarkocupicContaoFrontendUserNotification;
use Markocupic\RssFeedGeneratorBundle\MarkocupicRssFeedGeneratorBundle;
use Markocupic\SacEventToolBundle\ContaoManager\Plugin;
use Markocupic\SacEventToolBundle\MarkocupicSacEventToolBundle;

class PluginTest extends ContaoTestCase
{
    public function testInstantiation(): void
    {
        $this->assertInstanceOf(Plugin::class, new Plugin());
    }

    public function testGetBundles(): void
    {
        $plugin = new Plugin();

        /** @var array $bundles */
        $bundles = $plugin->getBundles(new DelegatingParser());

        $this->assertCount(3, $bundles);

		$this->assertInstanceOf(BundleConfig::class, $bundles[0]);
		$this->assertSame(Code4NixUriSigner::class, $bundles[0]->getName());

        $this->assertInstanceOf(BundleConfig::class, $bundles[1]);
        $this->assertSame(MarkocupicRssFeedGeneratorBundle::class, $bundles[1]->getName());

        $this->assertInstanceOf(BundleConfig::class, $bundles[2]);
        $this->assertSame(MarkocupicSacEventToolBundle::class, $bundles[2]->getName());

		$this->assertSame(
            [
				ContaoCoreBundle::class,
				ContaoCalendarBundle::class,
				Code4NixUriSigner::class,
				MarkocupicRssFeedGeneratorBundle::class,
				MarkocupicContaoFrontendUserNotification::class,
            ],
            $bundles[2]->getLoadAfter()
        );
    }
}
