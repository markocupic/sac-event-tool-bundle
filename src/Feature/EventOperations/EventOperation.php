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

namespace Markocupic\SacEventToolBundle\Feature\EventOperations;

/**
 * One operation of an event (edit, event list, preview, registrations, e-mail,
 * tour report, print report), see EventOperationsBuilder.
 *
 * The href is only set, if the user may execute the operation (state "enabled").
 */
final readonly class EventOperation
{
    /**
     * @param string                $name           One of the EventOperationsBuilder::OPERATION_* constants
     * @param string                $label          Text of the button (event dashboard) or of the dropdown item (my events dashboard)
     * @param string                $title          Title attribute, explains why the operation is greyed out
     * @param string                $iconClass      Font Awesome classes, e.g. "fa-solid fa-fw fa-print"
     * @param string|null           $iconModifier   Color of an enabled icon: "green" (done) or "red" (open), null for the default color
     * @param array<string, string> $linkAttributes Additional attributes of the link
     */
    public function __construct(
        public string $name,
        public EventOperationState $state,
        public string $label,
        public string $title,
        public string $iconClass,
        public string|null $href = null,
        public bool $primary = false,
        public string|null $iconModifier = null,
        public string|null $accessKey = null,
        public array $linkAttributes = ['data-turbo' => 'false'],
    ) {
        if (EventOperationState::Enabled === $state && (null === $href || '' === $href)) {
            throw new \InvalidArgumentException(\sprintf('The enabled operation "%s" needs a href.', $name));
        }

        if (EventOperationState::Enabled !== $state && null !== $href) {
            throw new \InvalidArgumentException(\sprintf('The operation "%s" is not enabled and must not have a href.', $name));
        }
    }

    public function isEnabled(): bool
    {
        return EventOperationState::Enabled === $this->state;
    }

    public function isDenied(): bool
    {
        return EventOperationState::Denied === $this->state;
    }

    public function isAvailable(): bool
    {
        return EventOperationState::NotAvailable !== $this->state;
    }

    /**
     * The Font Awesome classes including the color modifier
     * ("sacevt-op-icon--green", "sacevt-op-icon--red" or "sacevt-op-icon--disabled").
     */
    public function getIconClasses(): string
    {
        $classes = ['sacevt-op-icon', $this->iconClass];

        if (!$this->isEnabled()) {
            $classes[] = 'sacevt-op-icon--disabled';
        } elseif (null !== $this->iconModifier) {
            $classes[] = 'sacevt-op-icon--'.$this->iconModifier;
        }

        return implode(' ', $classes);
    }

    /**
     * @return array{name: string, state: string, label: string, title: string, icon_class: string, href: string|null, primary: bool, access_key: string|null, link_attributes: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state->value,
            'label' => $this->label,
            'title' => $this->title,
            'icon_class' => $this->getIconClasses(),
            'href' => $this->href,
            'primary' => $this->primary,
            'access_key' => $this->accessKey,
            'link_attributes' => $this->isEnabled() ? $this->linkAttributes : [],
        ];
    }
}
