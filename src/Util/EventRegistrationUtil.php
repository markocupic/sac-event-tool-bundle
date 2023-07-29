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

namespace Markocupic\SacEventToolBundle\Util;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Image;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventRegistrationUtil
{
    private Adapter $stringUtilAdapter;
    private Adapter $imageAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->imageAdapter = $this->framework->getAdapter(Image::class);
    }

    public function getSubscriptionStateIcon(CalendarEventsMemberModel $registrationModel): string
    {
        $icon = sprintf('%s/icons/subscription-states/%s.svg', Bundle::ASSET_DIR, $registrationModel->stateOfSubscription);
        $state = $this->translator->trans('MSC.'.$registrationModel->stateOfSubscription, [], 'contao_default');

        $strAlt = $state;
        $strAttributes = sprintf('title="%s"', $this->stringUtilAdapter->specialchars($state));

        return $this->imageAdapter->getHtml($icon, $strAlt, $strAttributes);
    }
}
