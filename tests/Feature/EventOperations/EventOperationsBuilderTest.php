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

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationState;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsInstructorInvoiceVoter;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventOperationsBuilderTest extends ContaoTestCase
{
    private const array ALL_PERMISSIONS = [
        'read' => true,
        CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS => true,
        CalendarEventsVoter::CAN_WRITE_EVENT => true,
        CalendarEventsInstructorInvoiceVoter::HAS_ACCESS => true,
    ];

    public function testReturnsAllOperationsInAFixedOrder(): void
    {
        $operations = $this->createBuilder(self::ALL_PERMISSIONS)->build($this->mockEvent());

        $this->assertSame(
            [
                EventOperationsBuilder::OPERATION_EDIT_EVENT,
                EventOperationsBuilder::OPERATION_EVENT_LISTING,
                EventOperationsBuilder::OPERATION_EVENT_PREVIEW,
                EventOperationsBuilder::OPERATION_REGISTRATION_LIST,
                EventOperationsBuilder::OPERATION_SEND_EMAIL,
                EventOperationsBuilder::OPERATION_TOUR_REPORT,
                EventOperationsBuilder::OPERATION_PRINT_REPORT,
            ],
            array_keys($operations),
        );
    }

    public function testEnablesAllOperationsWithAllPermissions(): void
    {
        $operations = $this->createBuilder(self::ALL_PERMISSIONS)->build($this->mockEvent(), 'sac_calendar');

        foreach ($operations as $name => $operation) {
            $this->assertSame(EventOperationState::Enabled, $operation->state, $name);
        }

        $this->assertSame('contao_backend?do=sac_calendar&table=tl_calendar_events&act=edit&id=5&rt=token&ref=ref1', $operations[EventOperationsBuilder::OPERATION_EDIT_EVENT]->href);
        $this->assertSame('contao_backend?do=sac_calendar&table=tl_calendar_events&id=2&rt=token&ref=ref1', $operations[EventOperationsBuilder::OPERATION_EVENT_LISTING]->href);
        $this->assertSame('https://example.org/preview', $operations[EventOperationsBuilder::OPERATION_EVENT_PREVIEW]->href);
        $this->assertSame('contao_backend?do=sac_calendar&table=tl_calendar_events_member&id=5&rt=token&ref=ref1', $operations[EventOperationsBuilder::OPERATION_REGISTRATION_LIST]->href);
        $this->assertSame('contao_backend?do=sac_calendar&table=tl_calendar_events&act=edit&call=writeTourReport&id=5&rt=token&ref=ref1', $operations[EventOperationsBuilder::OPERATION_TOUR_REPORT]->href);
        $this->assertSame('contao_backend?do=sac_calendar&table=tl_calendar_events_instructor_invoice&id=5&rt=token&ref=ref1', $operations[EventOperationsBuilder::OPERATION_PRINT_REPORT]->href);

        // The e-mail link is signed and contains the event id
        $emailHref = $operations[EventOperationsBuilder::OPERATION_SEND_EMAIL]->href;
        $this->assertStringStartsWith('/contao/event_participant_email?', $emailHref);

        parse_str((string) parse_url($emailHref, PHP_URL_QUERY), $query);
        $this->assertSame('5', $query['eventId'] ?? null);
        $this->assertSame('token', $query['rt'] ?? null);
        $this->assertArrayHasKey('_hash', $query);
    }

    public function testColorsTheTourReportIconByItsState(): void
    {
        $builder = $this->createBuilder(self::ALL_PERMISSIONS);

        $open = $builder->build($this->mockEvent(['filledInEventReportForm' => false]))[EventOperationsBuilder::OPERATION_TOUR_REPORT];
        $done = $builder->build($this->mockEvent(['filledInEventReportForm' => true]))[EventOperationsBuilder::OPERATION_TOUR_REPORT];

        $this->assertSame('red', $open->iconModifier);
        $this->assertSame('MSC.bhs_dashb_opLabel_createReport', $open->label);
        $this->assertSame('green', $done->iconModifier);
        $this->assertSame('MSC.bhs_dashb_opLabel_editReport', $done->label);
    }

    public function testDeniesAllOperationsWithoutReadAccessToTheEvent(): void
    {
        $permissions = ['read' => false] + self::ALL_PERMISSIONS;

        $operations = $this->createBuilder($permissions)->build($this->mockEvent());

        foreach ($operations as $name => $operation) {
            $this->assertSame(EventOperationState::Denied, $operation->state, $name);
            $this->assertNull($operation->href, $name);
        }

        $this->assertSame('MSC.bhs_dashb_opDenied|MSC.bhs_dashb_opLabel_registrationList', $operations[EventOperationsBuilder::OPERATION_REGISTRATION_LIST]->title);
    }

    public function testSendingEmailsRequiresThePermissionToAdministerTheRegistrations(): void
    {
        $permissions = [CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS => false] + self::ALL_PERMISSIONS;

        $operations = $this->createBuilder($permissions)->build($this->mockEvent());

        $this->assertSame(EventOperationState::Denied, $operations[EventOperationsBuilder::OPERATION_SEND_EMAIL]->state);
        $this->assertSame(EventOperationState::Enabled, $operations[EventOperationsBuilder::OPERATION_REGISTRATION_LIST]->state);
    }

    public function testTheTourReportRequiresWriteAccessAndPrintingRequiresAccessToTheInvoices(): void
    {
        $noWrite = $this->createBuilder([CalendarEventsVoter::CAN_WRITE_EVENT => false] + self::ALL_PERMISSIONS)->build($this->mockEvent());

        $this->assertSame(EventOperationState::Denied, $noWrite[EventOperationsBuilder::OPERATION_TOUR_REPORT]->state);
        $this->assertSame(EventOperationState::Enabled, $noWrite[EventOperationsBuilder::OPERATION_PRINT_REPORT]->state);

        $noInvoice = $this->createBuilder([CalendarEventsInstructorInvoiceVoter::HAS_ACCESS => false] + self::ALL_PERMISSIONS)->build($this->mockEvent());

        $this->assertSame(EventOperationState::Enabled, $noInvoice[EventOperationsBuilder::OPERATION_TOUR_REPORT]->state);
        $this->assertSame(EventOperationState::Denied, $noInvoice[EventOperationsBuilder::OPERATION_PRINT_REPORT]->state);
    }

    public function testOperationsThatDoNotExistForTheEventAreNotAvailable(): void
    {
        $operations = $this->createBuilder(self::ALL_PERMISSIONS, hasRegistrations: false, previewUrl: '')
            ->build($this->mockEvent(['eventType' => EventType::COURSE]))
        ;

        $this->assertSame(EventOperationState::NotAvailable, $operations[EventOperationsBuilder::OPERATION_EVENT_PREVIEW]->state);
        $this->assertSame('MSC.bhs_dashb_livePreviewDisabled|MSC.bhs_dashb_opLabel_livePreview', $operations[EventOperationsBuilder::OPERATION_EVENT_PREVIEW]->title);

        $this->assertSame(EventOperationState::NotAvailable, $operations[EventOperationsBuilder::OPERATION_SEND_EMAIL]->state);
        $this->assertSame(EventOperationState::NotAvailable, $operations[EventOperationsBuilder::OPERATION_TOUR_REPORT]->state);
        $this->assertSame(EventOperationState::NotAvailable, $operations[EventOperationsBuilder::OPERATION_PRINT_REPORT]->state);

        $this->assertSame(EventOperationState::Enabled, $operations[EventOperationsBuilder::OPERATION_EDIT_EVENT]->state);
        $this->assertSame(EventOperationState::Enabled, $operations[EventOperationsBuilder::OPERATION_EVENT_LISTING]->state);
        $this->assertSame(EventOperationState::Enabled, $operations[EventOperationsBuilder::OPERATION_REGISTRATION_LIST]->state);
    }

    public function testChecksTheReadAccessWithTheRecordOfTheEvent(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(
                static function (mixed $attribute, mixed $subject = null): bool {
                    if (ContaoCorePermissions::DC_PREFIX.'tl_calendar_events' === $attribute) {
                        return $subject instanceof ReadAction && '5' === $subject->getCurrentId() && '2' === $subject->getCurrentPid();
                    }

                    return false;
                },
            )
        ;

        $operations = $this->createBuilder([], authorizationChecker: $authorizationChecker)->build($this->mockEvent());

        $this->assertSame(EventOperationState::Enabled, $operations[EventOperationsBuilder::OPERATION_EDIT_EVENT]->state);
        $this->assertSame(EventOperationState::Denied, $operations[EventOperationsBuilder::OPERATION_SEND_EMAIL]->state);
    }

    /**
     * @param array<string, bool> $permissions "read" stands for the read access to the event (contao_dc.tl_calendar_events)
     */
    private function createBuilder(array $permissions, bool $hasRegistrations = true, string $previewUrl = 'https://example.org/preview', AuthorizationCheckerInterface|null $authorizationChecker = null): EventOperationsBuilder
    {
        if (null === $authorizationChecker) {
            $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
            $authorizationChecker
                ->method('isGranted')
                ->willReturnCallback(
                    static function (mixed $attribute) use ($permissions): bool {
                        if (ContaoCorePermissions::DC_PREFIX.'tl_calendar_events' === $attribute) {
                            return $permissions['read'] ?? false;
                        }

                        return $permissions[$attribute] ?? false;
                    },
                )
            ;
        }

        $calendarEventsUtil = $this->createMock(CalendarEventsUtil::class);
        $calendarEventsUtil
            ->method('generateEventPreviewUrl')
            ->willReturn($previewUrl)
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn($hasRegistrations ? 1 : false)
        ;

        $csrfTokenManager = $this->createMock(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('token')
        ;

        $request = Request::create('/contao?do=calendar');
        $request->attributes->set('_contao_referer_id', 'ref1');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(
                static fn (string $name, array $params = []): string => 'contao_backend' === $name
                    ? 'contao_backend?'.http_build_query($params)
                    : '/contao/event_participant_email',
            )
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $params = []): string => [] === $params ? $id : $id.'|'.implode(',', $params))
        ;

        return new EventOperationsBuilder(
            $authorizationChecker,
            $calendarEventsUtil,
            $connection,
            $csrfTokenManager,
            $requestStack,
            $router,
            $translator,
            new UriSigner('secret'),
            new UrlParser(),
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockEvent(array $properties = []): CalendarEventsModel
    {
        $properties += [
            'id' => 5,
            'pid' => 2,
            'eventType' => EventType::TOUR,
            'filledInEventReportForm' => false,
        ];

        $event = $this->mockClassWithProperties(CalendarEventsModel::class, $properties);
        $event
            ->method('row')
            ->willReturn($properties)
        ;

        return $event;
    }
}
