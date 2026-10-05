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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;

/**
 * Event feedback forms (tl_form.isSacEventFeedbackForm) always store their values in tl_event_feedback.
 */
#[AsHook('prepareFormData', priority: 100)]
class PrepareFormDataListener
{
    public function __invoke(array $submitted, array $labels, array|null $fields, Form $form): void
    {
        if ($form->isSacEventFeedbackForm) {
            $form->storeValues = '1';
            $form->targetTable = 'tl_event_feedback';
        }
    }
}
