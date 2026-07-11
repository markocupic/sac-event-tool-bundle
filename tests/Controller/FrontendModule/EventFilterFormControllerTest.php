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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventFilterFormController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventFilterFormControllerTest extends TestCase
{
    private EventFilterFormController $controller;

    private MockObject|null $urlParserMock;

    protected function setUp(): void
    {
        $this->urlParserMock = $this->createMock(UrlParser::class);
        $this->controller = new EventFilterFormController(
            $this->createMock(ContaoFramework::class),
            $this->createMock(TranslatorInterface::class),
            $this->urlParserMock,
            'en',
        );
    }

    public function testSanitizeUrlAddsDefaultGetUpcomingParameter(): void
    {
        $request = Request::create('https://localhost/test');

        $this->urlParserMock
            ->expects($this->once())
            ->method('addQueryString')
            ->with('getUpcoming=1', 'https://localhost/test')
            ->willReturn('https://localhost/test?getUpcoming=1')
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?getUpcoming=1', $sanitizedUrl);
    }

    public function testSanitizeUrlRemovesInvalidGetUpcomingParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['getUpcoming' => 'invalid']);

        $this->urlParserMock
            ->expects($this->once())
            ->method('removeQueryString')
            ->with(['getUpcoming'], 'https://localhost/test?getUpcoming=invalid')
            ->willReturn('https://localhost/test')
        ;

        $this->urlParserMock
            ->expects($this->once())
            ->method('addQueryString')
            ->with('getUpcoming=1', 'https://localhost/test')
            ->willReturn('https://localhost/test?getUpcoming=1')
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?getUpcoming=1', $sanitizedUrl);
    }

    public function testSanitizeUrlRemovesDateParamsWhenGetUpcomingIsSet(): void
    {
        $invokedCount = $this->exactly(3);

        $this->urlParserMock
            ->expects($invokedCount)
            ->method('removeQueryString')
            ->willReturnCallback(
                function ($queryKey, $url) use ($invokedCount): string {
                    if (1 === $invokedCount->getInvocationCount()) {
                        $this->assertSame([['dateStart'], 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01&getUpcoming=1&year=2025'], [$queryKey, $url]);

                        return 'https://localhost/test?dateEnd=2025-12-31&getUpcoming=1&year=2025';
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        $this->assertSame([['dateEnd'], 'https://localhost/test?dateEnd=2025-12-31&getUpcoming=1&year=2025'], [$queryKey, $url]);

                        return 'https://localhost/test?getUpcoming=1&year=2025';
                    }

                    if (3 === $invokedCount->getInvocationCount()) {
                        $this->assertSame([['year'], 'https://localhost/test?getUpcoming=1&year=2025'], [$queryKey, $url]);

                        return 'https://localhost/test?getUpcoming=1';
                    }

                    throw new \LogicException('');
                },
            )
        ;

        $request = Request::create('https://localhost/test', 'GET', ['getUpcoming' => '1', 'dateStart' => '2025-01-01', 'dateEnd' => '2025-12-31', 'year' => '2025']);
        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?getUpcoming=1', $sanitizedUrl);
    }

    public function testSanitizeUrlValidatesYearParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['year' => '1900']);

        $this->urlParserMock
            ->expects($this->once())
            ->method('removeQueryString')
            ->with(['year'], 'https://localhost/test?year=1900')
            ->willReturn('https://localhost/test')
        ;

        $this->urlParserMock
            ->expects($this->once())
            ->method('addQueryString')
            ->with('getUpcoming=1', 'https://localhost/test')
            ->willReturn('https://localhost/test?getUpcoming=1')
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?getUpcoming=1', $sanitizedUrl);
    }

    public function testSanitizeUrlValidatesInvalidDateStartParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['dateStart' => 'invalid-date']);

        $this->urlParserMock
            ->expects($this->once())
            ->method('removeQueryString')
            ->with(['dateStart'], 'https://localhost/test?dateStart=invalid-date')
            ->willReturn('https://localhost/test')
        ;

        $this->urlParserMock
            ->expects($this->once())
            ->method('addQueryString')
            ->with('getUpcoming=1', 'https://localhost/test')
            ->willReturn('https://localhost/test?getUpcoming=1')
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?getUpcoming=1', $sanitizedUrl);
    }

    public function testSanitizeUrlAdjustsDateStartToYearParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['dateStart' => '2025-01-01', 'dateEnd' => '2024-12-31', 'year' => '2024']);

        $this->urlParserMock
            ->expects($this->exactly(1))
            ->method('removeQueryString')
            ->with(['dateStart'], 'https://localhost/test?dateEnd=2024-12-31&dateStart=2025-01-01&year=2024')
            ->willReturn('https://localhost/test?dateEnd=2024-12-31&year=2024')
        ;

        $this->urlParserMock
            ->expects($this->exactly(1))
            ->method('addQueryString')
            ->with('dateStart=2024-01-01', 'https://localhost/test?dateEnd=2024-12-31&year=2024')
            ->willReturn('https://localhost/test?dateEnd=2024-12-31&year=2024&dateStart=2024-01-01')
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?dateEnd=2024-12-31&dateStart=2024-01-01&year=2024', $sanitizedUrl);
    }

    public function testSanitizeUrlDateEndMustHaveSameYearNumberAsDateStart(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['dateStart' => '2025-01-01', 'dateEnd' => '2026-12-31']);

        $this->urlParserMock
            ->expects($this->exactly(1))
            ->method('removeQueryString')
            ->with(['dateEnd', 'year'], 'https://localhost/test?dateEnd=2026-12-31&dateStart=2025-01-01')
            ->willReturn('https://localhost/test?dateStart=2025-01-01')
        ;

        $invokedCount = $this->exactly(2);

        $this->urlParserMock
            ->expects($invokedCount)
            ->method('addQueryString')
            ->willReturnCallback(
                function ($queryString, $url) use ($invokedCount): string {
                    if (1 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['dateEnd=2025-12-31', 'https://localhost/test?dateStart=2025-01-01'], [$queryString, $url]);

                        return 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01';
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['year=2025', 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01'], [$queryString, $url]);

                        return 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01&year=2025';
                    }

                    throw new \LogicException('');
                },
            )
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01&year=2025', $sanitizedUrl);
    }

    public function testSanitizeUrlValidatesMissingDateEndParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['dateStart' => '2025-02-01', 'year' => '2025']);

        $this->urlParserMock
            ->expects($this->once())
            ->method('removeQueryString')
            ->with(['dateEnd', 'year'], 'https://localhost/test?dateStart=2025-02-01&year=2025')
            ->willReturn('https://localhost/test?dateStart=2025-02-01')
        ;

        $invokedCount = $this->exactly(2);

        $this->urlParserMock
            ->expects($invokedCount)
            ->method('addQueryString')
            ->willReturnCallback(
                function ($queryString, $url) use ($invokedCount): string {
                    if (1 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['dateEnd=2025-12-31', 'https://localhost/test?dateStart=2025-02-01'], [$queryString, $url]);

                        return 'https://localhost/test?dateStart=2025-02-01&dateEnd=2025-12-31';
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['year=2025', 'https://localhost/test?dateStart=2025-02-01&dateEnd=2025-12-31'], [$queryString, $url]);

                        return 'https://localhost/test?dateStart=2025-02-01&dateEnd=2025-12-31&year=2025';
                    }

                    throw new \LogicException('');
                },
            )
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?dateEnd=2025-12-31&dateStart=2025-02-01&year=2025', $sanitizedUrl);
    }

    public function testSanitizeUrlValidatesMissingDateStartParameter(): void
    {
        $request = Request::create('https://localhost/test', 'GET', ['dateEnd' => '2025-12-31', 'year' => '2025']);

        $this->urlParserMock
            ->expects($this->once())
            ->method('removeQueryString')
            ->with(['dateStart', 'year'], 'https://localhost/test?dateEnd=2025-12-31&year=2025')
            ->willReturn('https://localhost/test?dateEnd=2025-12-31')
        ;

        $invokedCount = $this->exactly(2);

        $this->urlParserMock
            ->expects($invokedCount)
            ->method('addQueryString')
            ->willReturnCallback(
                function ($queryString, $url) use ($invokedCount): string {
                    if (1 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['dateStart=2025-01-01', 'https://localhost/test?dateEnd=2025-12-31'], [$queryString, $url]);

                        return 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01';
                    }

                    if (2 === $invokedCount->getInvocationCount()) {
                        $this->assertSame(['year=2025', 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01'], [$queryString, $url]);

                        return 'https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01&year=2025';
                    }

                    throw new \LogicException('');
                },
            )
        ;

        $sanitizedUrl = $this->invokeSanitizeUrl($request);

        $this->assertSame('https://localhost/test?dateEnd=2025-12-31&dateStart=2025-01-01&year=2025', $sanitizedUrl);
    }

    private function invokeSanitizeUrl(Request $request): string
    {
        // Since PHP 8.1 protected members are reflection-accessible without setAccessible().
        return (new \ReflectionMethod(EventFilterFormController::class, 'sanitizeUrl'))->invoke($this->controller, $request);
    }
}
