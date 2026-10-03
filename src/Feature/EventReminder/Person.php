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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder;

use Contao\UserModel;

/**
 * Framework-independent representation of an instructor or registration coordinator.
 */
final readonly class Person
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $phone = '',
        public string $mobile = '',
    ) {
    }

    public static function fromUserModel(UserModel $user): self
    {
        return new self(
            $user->id,
            trim($user->name),
            trim($user->email),
            trim((string) $user->phone),
            trim((string) $user->mobile),
        );
    }

    public function hasEmail(): bool
    {
        return '' !== $this->email;
    }
}
