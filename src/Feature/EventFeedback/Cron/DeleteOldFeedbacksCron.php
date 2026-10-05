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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Weekly: deletes feedbacks and feedback requests older than
 * sacevt.feature.event_feedback.delete_feedbacks_after (days).
 */
#[AsCronJob('weekly')]
readonly class DeleteOldFeedbacksCron
{
    public function __construct(
        private Connection $connection,
        #[Autowire(param: 'sacevt.feature.event_feedback.delete_feedbacks_after')]
        private int $deleteFeedbacksAfter,
    ) {
    }

    public function __invoke(): void
    {
        $limit = (new \DateTimeImmutable('today'))->modify(\sprintf('-%d day', $this->deleteFeedbacksAfter))->getTimestamp();

        $this->connection->executeStatement('DELETE FROM tl_event_feedback WHERE dateAdded < ?', [$limit]);
        $this->connection->executeStatement('DELETE FROM tl_event_feedback_reminder WHERE dateAdded < ?', [$limit]);
    }
}
