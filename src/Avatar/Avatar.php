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

namespace Markocupic\SacEventToolBundle\Avatar;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\UserModel;
use Symfony\Component\Filesystem\Path;

class Avatar
{
    private Adapter $filesModelAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
        private readonly string $sacevtAvatarFemale,
        private readonly string $sacevtAvatarMale,
        private readonly string $sacevtAvatarOther,
    ) {
        $this->filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
    }

    public function getAvatarResourcePath(MemberModel|UserModel|null $userModel, $makeAbsolute = false): string
    {
        $customAvatarPath = $this->getValidatedAvatarPath($userModel);

        if (null !== $customAvatarPath) {
            return $this->formatPath($customAvatarPath, $makeAbsolute);
        }

        $defaultAvatarPath = $this->getDefaultAvatarPath($userModel);

        return $this->formatPath($defaultAvatarPath, $makeAbsolute);
    }

    private function getValidatedAvatarPath(object|null $userModel): string|null
    {
        if (empty($userModel->avatar)) {
            return null;
        }

        $objFiles = $this->filesModelAdapter->findByUuid($userModel->avatar);

        if (null === $objFiles) {
            return null;
        }

        if (!is_file(Path::join($this->projectDir, $objFiles->path))) {
            return null;
        }

        return $objFiles->path;
    }

    private function getDefaultAvatarPath(object|null $userModel): string
    {
        if (empty($userModel)) {
            return $this->sacevtAvatarOther;
        }

        return match ($userModel->gender) {
            'female' => $this->sacevtAvatarFemale,
            'male' => $this->sacevtAvatarMale,
            default => $this->sacevtAvatarOther,
        };
    }

    private function formatPath(string $path, bool $makeAbsolute): string
    {
        return $makeAbsolute ? Path::makeAbsolute($path, $this->projectDir) : Path::makeRelative($path, $this->projectDir);
    }
}
