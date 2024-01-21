<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Tests\ContaoManager;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Controller\Api\EventApiController;
use Symfony\Component\HttpFoundation\Request;

class EventApiControllerTest extends ContaoTestCase
{

    /**
     * @dataProvider getQueryParamsFromRequestProvider
     */
    public function testGetQueryParamsFromRequest(string $uri, string $expected): void
    {
        $hostname = 'https://www.domain.com';
        $request = Request::create($hostname.'?'.$uri);

        // Get the result
        $params = $this->getFilterParamsFromController($request);

        $this->assertSame($expected, json_encode($params));
    }

	private function getFilterParamsFromController(Request $request): array{
		$framework = $this->mockContaoFramework();
		$connection = $this->createMock(Connection::class);

		$controller = new EventApiController($framework, $connection);

		// Make protected method accessible
		$method = new \ReflectionMethod(EventApiController::class, 'getQueryParamsFromRequest');
		$method->setAccessible(true);

		// Get the result
		return $method->invoke($controller, $request);
	}

    private function getQueryParamsFromRequestProvider(): \Generator
    {
        yield 'request 1' => [
            'organizers[]=17&eventType[]="tour","lastMinuteTour","generalEvent"&suitableForBeginners=1&publicTransportEvent=1&tourType=8&courseType=&courseId=99&year=2021&dateStart=2021-12-08&textSearch=Lorem ipsum&eventId=99&username=&calendarIds[]=54&calendarIds[]=49&limit=50&offset=0&fields[]=id&fields[]=title&fields[]=eventOrganizerLogos||50',
            '{"organizers":["17"],"eventType":["\"tour\",\"lastMinuteTour\",\"generalEvent\""],"calendarIds":["54","49"],"fields":["id","title","eventOrganizerLogos||50"],"arrIds":null,"offset":0,"limit":50,"tourType":8,"courseType":0,"year":2021,"courseId":"99","eventId":"99","dateStart":"2021-12-08","dateEnd":null,"textSearch":"Lorem ipsum","username":"","suitableForBeginners":"1","publicTransportEvent":"1"}',
        ];
    }
}
