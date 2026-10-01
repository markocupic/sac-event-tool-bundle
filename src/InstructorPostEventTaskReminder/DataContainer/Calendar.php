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

namespace Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\NotificationType\InstructorPostEventTaskReminderNotificationType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * tl_calendar callbacks for the instructor post-event task reminder settings.
 */
readonly class Calendar
{
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Only offer notifications of type "instructor_post_event_task_reminder".
     *
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.instructorPostEventTaskReminderNotification.options')]
    public function getNotificationOptions(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM tl_nc_notification WHERE type = ? ORDER BY title',
            [InstructorPostEventTaskReminderNotificationType::NAME],
        );

        $options = [];

        foreach ($rows as $row) {
            $options[(int) $row['id']] = $row['title'];
        }

        return $options;
    }

    /**
     * The lookback period must be longer than the grace period,
     * otherwise no event could ever become due.
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.instructorPostEventTaskReminderLookback.save')]
    public function validateLookback(mixed $value, DataContainer $dc): mixed
    {
        $request = $this->requestStack->getCurrentRequest();

        // The grace period is submitted with the same form; fall back to the stored value
        $firstOffset = $request?->request->get('instructorPostEventTaskReminderFirstOffset') ?? $dc->activeRecord?->instructorPostEventTaskReminderFirstOffset;

        if (null !== $firstOffset && (int) $value <= (int) $firstOffset) {
            throw new \RuntimeException($this->translator->trans('ERR.instructorPostEventTaskReminderLookbackTooShort', [], 'contao_default'));
        }

        return $value;
    }
}
