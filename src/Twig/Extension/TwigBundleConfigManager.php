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

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Markocupic\SacEventToolBundle\Config\Bundle;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigBundleConfigManager extends AbstractExtension
{

	public function getFunctions(): array
	{
		return [
			new TwigFunction('sacevt_asset_dir', [$this, 'getBundleAssetDir']),
		];
	}

	public function getBundleAssetDir(): string
	{
		return Bundle::ASSET_DIR;
	}

}
