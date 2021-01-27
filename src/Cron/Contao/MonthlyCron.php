<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cron\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SacEventToolBundle\User\BackendUser\ReplaceDefaultPassword;

/**
 * Class MonthlyCron.
 */
class MonthlyCron
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * MonthlyCron constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * replace default password.
     */
    public function replaceDefaultPasswordAndSendNew(): void
    {
        /** @var ReplaceDefaultPassword $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\User\BackendUser\ReplaceDefaultPassword');
        $cron->replaceDefaultPasswordAndSendNew();
    }
}
