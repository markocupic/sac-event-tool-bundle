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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventOperations;

use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperation;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationState;
use PHPUnit\Framework\TestCase;

final class EventOperationTest extends TestCase
{
    public function testEnabledOperationNeedsAHref(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EventOperation('print-report', EventOperationState::Enabled, 'Drucken', 'Drucken', 'fa-print');
    }

    public function testOperationThatIsNotEnabledMustNotHaveAHref(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EventOperation('print-report', EventOperationState::Denied, 'Drucken', 'Drucken', 'fa-print', '/contao?do=calendar');
    }

    public function testIconClassesOfAnEnabledOperation(): void
    {
        $operation = new EventOperation('create-or-edit-report', EventOperationState::Enabled, 'Tourrapport', 'Tourrapport', 'fa-solid fa-comment-pen', '/contao', iconModifier: 'green');

        $this->assertTrue($operation->isEnabled());
        $this->assertTrue($operation->isAvailable());
        $this->assertSame('sacevt-op-icon fa-solid fa-comment-pen sacevt-op-icon--green', $operation->getIconClasses());
    }

    public function testDisabledOperationsAreGreyedOutWithoutColorModifier(): void
    {
        $denied = new EventOperation('create-or-edit-report', EventOperationState::Denied, 'Tourrapport', 'Keine Berechtigung', 'fa-comment-pen', iconModifier: 'red');
        $notAvailable = new EventOperation('create-or-edit-report', EventOperationState::NotAvailable, 'Tourrapport', 'Nicht verfügbar', 'fa-comment-pen', iconModifier: 'red');

        $this->assertTrue($denied->isDenied());
        $this->assertTrue($denied->isAvailable());
        $this->assertFalse($notAvailable->isAvailable());

        $this->assertSame('sacevt-op-icon fa-comment-pen sacevt-op-icon--disabled', $denied->getIconClasses());
        $this->assertSame('sacevt-op-icon fa-comment-pen sacevt-op-icon--disabled', $notAvailable->getIconClasses());
    }

    public function testToArray(): void
    {
        $enabled = new EventOperation('event-preview', EventOperationState::Enabled, 'Vorschau', 'Event Vorschau', 'fa-presentation-screen', 'https://preview', accessKey: 'p', linkAttributes: ['target' => '_blank']);
        $denied = new EventOperation('event-preview', EventOperationState::Denied, 'Vorschau', 'Vorschau: keine Berechtigung', 'fa-presentation-screen', linkAttributes: ['target' => '_blank']);

        $this->assertSame(
            [
                'name' => 'event-preview',
                'state' => 'enabled',
                'label' => 'Vorschau',
                'title' => 'Event Vorschau',
                'icon_class' => 'sacevt-op-icon fa-presentation-screen',
                'href' => 'https://preview',
                'primary' => false,
                'access_key' => 'p',
                'link_attributes' => ['target' => '_blank'],
            ],
            $enabled->toArray(),
        );

        // No link attributes without a link
        $this->assertSame('denied', $denied->toArray()['state']);
        $this->assertNull($denied->toArray()['href']);
        $this->assertSame([], $denied->toArray()['link_attributes']);
    }
}
