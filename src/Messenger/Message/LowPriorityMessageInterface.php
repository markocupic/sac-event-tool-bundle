<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Messenger\Message;

/**
 * Marker interface for messages that should be dispatched to the
 * "contao_prio_low" transport.
 *
 * Contao 6 dropped its own priority marker interfaces. The routing is
 * registered in MarkocupicSacEventToolExtension::prepend(), which works
 * with Contao 5.3 (Symfony 6.4) and Contao 6 alike.
 */
interface LowPriorityMessageInterface
{
}
