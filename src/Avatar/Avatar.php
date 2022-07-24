<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Avatar;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\PictureFactory;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\Model;
use Contao\UserModel;

class Avatar
{
    private ContaoFramework $framework;
    private PictureFactory $pictureFactory;
    private string $projectDir;
    private string $resAvatarFemale;
    private string $resAvatarMale;

    public function __construct(ContaoFramework $framework, PictureFactory $pictureFactory, string $projectDir, string $resAvatarFemale, string $resAvatarMale)
    {
        $this->framework = $framework;
        $this->pictureFactory = $pictureFactory;
        $this->projectDir = $projectDir;
        $this->resAvatarFemale = $resAvatarFemale;
        $this->resAvatarMale = $resAvatarMale;
    }

    /**
     * @param Model $userModel
     *
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
            return $this->resAvatarFemale;
        }

        if ('male' === $userModel->gender) {
            return $this->resAvatarMale;
        }

        if ('other' === $userModel->gender) {
            return $this->resAvatarMale;
        }

        return $this->resAvatarMale;
    }
}
