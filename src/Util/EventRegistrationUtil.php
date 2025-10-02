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
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventRegistrationUtil
{
    private Adapter $dateAdapter;

    private Adapter $stringUtilAdapter;

    private Adapter $imageAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
        $this->dateAdapter = $this->framework->getAdapter(Date::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->imageAdapter = $this->framework->getAdapter(Image::class);
    }

    public function getSubscriptionStateIcon(CalendarEventsMemberModel $registrationModel): string
    {
        $icon = \sprintf('%s/icons/subscription-states/%s.svg', Bundle::ASSET_DIR, $registrationModel->stateOfSubscription);
        $state = $this->translator->trans('MSC.'.$registrationModel->stateOfSubscription, [], 'contao_default');

        $strAlt = $this->stringUtilAdapter->specialchars($registrationModel->stateOfSubscription);
        $strAttributes = \sprintf('title="%s"', $this->stringUtilAdapter->specialchars($state));

        return $this->imageAdapter->getHtml($icon, $strAlt, $strAttributes);
    }

    /**
     * @param int|null $year if null, the year of the event will be used
     */
    public function getAgeGroup(CalendarEventsMemberModel $registrationModel, int|null $year = null): string
    {
        if ('' === $registrationModel->dateOfBirth) {
            return '';
        }

        $event = CalendarEventsModel::findById($registrationModel->eventId);

        if (null === $year) {
            if (null === $event || null === $event->startDate) {
                return '';
            }
            $year = (int) $this->dateAdapter->parse('Y', (int) $event->startDate);
        }

        $age = $this->getAgeAtEndOfYear($registrationModel, $year);

        return match (true) {
            $age <= 20 => 'J+S',
            $age <= 22 => 'Jugend',
            default => '',
        };
    }

    private function getAgeAtEndOfYear(CalendarEventsMemberModel $registrationModel, int $year): int
    {
        $birthTimestamp = (int) $registrationModel->dateOfBirth;
        $birthDate = (new \DateTimeImmutable())->setTimestamp($birthTimestamp);
        $endOfYear = new \DateTimeImmutable("$year-12-31");

        return $endOfYear->diff($birthDate)->y;
    }
}
