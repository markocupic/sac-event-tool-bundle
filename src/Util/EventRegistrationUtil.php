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

namespace Markocupic\SacEventToolBundle\Util;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventRegistrationUtil
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Packages $packages,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSubscriptionStateIcon(CalendarEventsMemberModel $registrationModel): string
    {
        $iconPath = \sprintf('icons/subscription-states/%s.svg', $registrationModel->stateOfSubscription);
        $icon = $this->packages->getPackage(Bundle::PACKAGE_NAME)->getUrl($iconPath);
        $state = $this->translator->trans('MSC.'.$registrationModel->stateOfSubscription, [], 'contao_default');

        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $strAlt = $stringUtil->specialchars($registrationModel->stateOfSubscription);
        $strAttributes = \sprintf('title="%s"', $stringUtil->specialchars($state));

        return $this->framework->getAdapter(Image::class)->getHtml($icon, $strAlt, $strAttributes);
    }

    /**
     * @param int|null $year if null, the year of the event will be used
     */
    public function getAgeGroup(CalendarEventsMemberModel $registrationModel, int|null $year = null): string
    {
        if ('' === $registrationModel->dateOfBirth) {
            return '';
        }

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($registrationModel->eventId);

        if (null === $year) {
            if (null === $event || null === $event->startDate) {
                return '';
            }

            $year = (int) $this->framework->getAdapter(Date::class)->parse('Y', (int) $event->startDate);
        }

        return AgeGroupUtil::getAgeGroup((int) $registrationModel->dateOfBirth, $year);
    }
}
