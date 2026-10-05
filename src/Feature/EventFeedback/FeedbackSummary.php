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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback;

use Contao\CalendarEventsModel;
use Contao\FormFieldModel;
use Contao\FormModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;

/**
 * Anonymous summary of all feedbacks of an event:
 * - choice fields (select, radio, checkbox): how many participants chose each option
 * - text fields (textarea): all answers
 */
class FeedbackSummary
{
    /**
     * @var array<string, array{label: string, values: array<string, array{label: string, count: int}>}>
     */
    private array $choiceFields = [];

    /**
     * @var array<string, array{label: string, values: list<string>}>
     */
    private array $textFields = [];

    private int $count = 0;

    public function __construct(private readonly CalendarEventsModel $event)
    {
        $this->collect();
    }

    public function getEvent(): CalendarEventsModel
    {
        return $this->event;
    }

    public function countFeedbacks(): int
    {
        return $this->count;
    }

    /**
     * @return array<string, array{label: string, values: array<string, array{label: string, count: int}>}>
     */
    public function getChoiceFields(): array
    {
        return $this->choiceFields;
    }

    /**
     * @return array<string, array{label: string, values: list<string>}>
     */
    public function getTextFields(): array
    {
        return $this->textFields;
    }

    private function collect(): void
    {
        $feedbacks = EventFeedbackModel::findByPid($this->event->id);

        if (null === $feedbacks) {
            return;
        }

        foreach ($feedbacks as $feedback) {
            if (null === ($form = FormModel::findById($feedback->form))) {
                continue;
            }

            ++$this->count;

            foreach (FormFieldModel::findByPid($form->id) ?? [] as $formField) {
                if ($formField->invisible || '' === (string) $formField->name) {
                    continue;
                }

                $name = (string) $formField->name;
                $value = trim((string) $feedback->{$name});

                if (\in_array($formField->type, ['select', 'checkbox', 'radio'], true)) {
                    $this->addChoiceField($formField);

                    if ('' !== $value && isset($this->choiceFields[$name]['values'][$value])) {
                        ++$this->choiceFields[$name]['values'][$value]['count'];
                    }
                } elseif ('textarea' === $formField->type) {
                    $this->textFields[$name] ??= ['label' => (string) $formField->label, 'values' => []];

                    if ('' !== $value) {
                        $this->textFields[$name]['values'][] = htmlspecialchars_decode($value);
                    }
                }
            }
        }
    }

    private function addChoiceField(FormFieldModel $formField): void
    {
        $name = (string) $formField->name;

        if (isset($this->choiceFields[$name])) {
            return;
        }

        $values = [];

        foreach (StringUtil::deserialize($formField->options, true) as $option) {
            if ('' !== (string) ($option['value'] ?? '')) {
                $values[(string) $option['value']] = ['label' => (string) $option['label'], 'count' => 0];
            }
        }

        $this->choiceFields[$name] = ['label' => (string) $formField->label, 'values' => $values];
    }
}
