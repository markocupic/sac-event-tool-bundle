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

use Contao\CoreBundle\Messenger\Message\LowPriorityMessageInterface;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\DocxTemplator\OutputType;

readonly class GenerateTourListBookletMessage implements LowPriorityMessageInterface
{
    public function __construct(
        private array $ids,
        private OutputType $outputType,
        private string $filename,
        private FrontendUser $user,
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getOutputType(): OutputType
    {
        return $this->outputType;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getUser(): FrontendUser
    {
        return $this->user;
    }
}
