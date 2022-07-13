<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment as TwigEnvironment;
use function Safe\json_encode;

class TestController extends AbstractController
{
    private Connection $connection;
    private ContaoFramework $framework;
    private TwigEnvironment $twig;

    public function __construct(Connection $connection, ContaoFramework $framework, TwigEnvironment $twig)
    {
        $this->connection = $connection;
        $this->framework = $framework;
        $this->twig = $twig;
    }

    /**
     * @Route("/test", name="markocupic_sac_evt_test", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function testAction()
    {
        $this->framework->initialize(true);

        $arrEvents = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events WHERE startDate > ? LIMIT 0,20', [time()]);

        while (false !== ($arrEvent = $stmt->fetchAssociative())) {
            $picture = null;
            $uuid = $arrEvent['singleSRC'] ? StringUtil::binToUuid($arrEvent['singleSRC']) : null;

            if ($uuid) {
                $it = sprintf('{{picture::%s?size=%s}}', $uuid, '22');
                $picture = Controller::replaceInsertTags($it);
            }

            $arrEvents[] = [
                'id' => $arrEvent['id'],
                'title' => $arrEvent['title'],
                'singleSRC' => $arrEvent['singleSRC'] ? StringUtil::binToUuid($arrEvent['singleSRC']) : null,
                'picture' => base64_encode($picture),
            ];
        }

        return new Response($this->twig->render(
            '@MarkocupicSacEventTool/test.html.twig',
            [
                'data' => json_encode([
                    'events' => $arrEvents,
                ]),
            ]
        ));
    }

    /**
     * @Route("/_imagetest/{uuid}", name="markocupic_sac_evt_imagetest", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function imageAction($uuid): JsonResponse
    {
        $this->framework->initialize(true);
        $it = sprintf('{{picture::%s?size=%s}}', $uuid, '22');

        $json = [
            'image' => Controller::replaceInsertTags($it),
        ];

        return new JsonResponse($json);
    }
}
