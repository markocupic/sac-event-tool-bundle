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

namespace Markocupic\SacEventToolBundle\Messenger\Message;

use Contao\CoreBundle\Messenger\Message\LowPriorityMessageInterface;
use Contao\FrontendUser;

readonly class GenerateTourListBookletMessage implements LowPriorityMessageInterface
{
    private function __construct(
        private array $ids,
        private string $outputFormat,
        private string $filename,
        private FrontendUser $user,
    ) {
    }

    public static function create(array $ids, string $outputFormat, string $filename, FrontendUser $user): self
    {
        return new self($ids, $outputFormat, $filename, $user);
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
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
