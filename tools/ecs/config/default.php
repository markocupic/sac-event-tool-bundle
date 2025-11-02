<?php

declare(strict_types=1);

use Contao\EasyCodingStandard\Set\SetList;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return ECSConfig::configure()
	->withSets([SetList::CONTAO])
	->withPaths([
		__DIR__ . '/../../src',
	])
	->withSkip([
		\Contao\EasyCodingStandard\Fixer\CommentLengthFixer::class          => ['*.php'],
		\PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer::class  => [
			'*/DependencyInjection/Configuration.php',
		],
		\SlevomatCodingStandard\Sniffs\Variables\UnusedVariableSniff::class => [
			//'core-bundle/tests/Session/Attribute/ArrayAttributeBagTest.php',
			'*/Controller/Cloudconvert/Sandbox/WebhookController.php',
			'*/Pdf/WorkshopTCPDF.php',
		],
		__DIR__ . '/../../src/Controller/Cloudconvert/Sandbox/WebhookController.php',

	])
	->withRootFiles()
	->withParallel()
	->withSpacing(Option::INDENTATION_SPACES, "\n")
	->withConfiguredRule(HeaderCommentFixer::class, [
		'header' => "This file is part of SAC Event Tool Bundle.\n\n(c) Marko Cupic <m.cupic@gmx.ch>\n@license GPL-3.0-or-later\nFor the full copyright and license information,\nplease view the LICENSE file that was distributed with this source code.\n@link https://github.com/markocupic/sac-event-tool-bundle",
	])
	->withCache(sys_get_temp_dir() . '/ecs/markocupic/sac-event-tool-bundle');
