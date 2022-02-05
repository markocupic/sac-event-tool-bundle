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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\BackendUser;
use Contao\Image;
use Contao\StringUtil;
use Symfony\Component\Security\Core\Security;

class TourDifficultyCategory
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Return the edit header button.
     *
     * @Callback(table="tl_tour_difficulty_category", target="operations.editheader.button")
     */
    public function editHeaderButton(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        return $user->canEditFieldsOf('tl_tour_difficulty_category') ? '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }
}
