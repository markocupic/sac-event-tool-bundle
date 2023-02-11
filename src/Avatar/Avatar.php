<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Avatar;

use Contao\FilesModel;
use Contao\MemberModel;
use Contao\UserModel;

class Avatar
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $sacevtAvatarFemale,
        private readonly string $sacevtAvatarMale,
    ) {
    }

    /**
     * @todo serve an avatar if gender === 'other'
     */
    public function getAvatarResourcePath(MemberModel|UserModel $userModel): string
    {
        if (!empty($userModel->avatar)) {
            $objFiles = FilesModel::findByUuid($userModel->avatar);

            if (null !== $objFiles) {
                if (is_file($this->projectDir.'/'.$objFiles->path)) {
                    return $objFiles->path;
                }
            }
        }

        if ('female' === $userModel->gender) {
            return $this->sacevtAvatarFemale;
        }

        if ('male' === $userModel->gender) {
            return $this->sacevtAvatarMale;
        }
        //elseif ('other' === $userModel->gender) {
        //return $this->sacevtAvatarOther;
        //}

        return $this->sacevtAvatarMale;
    }
}
