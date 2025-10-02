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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsFrontendModule(EventListController::TYPE, category: 'sac_event_tool_frontend_modules')]
class EventListController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_list';

    protected ModuleModel|null $model = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->model = $model;

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Add or remove the event from the like-list
        $this->processFavoriteEventToggle($request);

        // 	Extract the API parameters from the request
        $apiParam = $this->extractApiParameters($request);

        // 	Extract the picture ID from the module model
        $pictureId = $this->extractPictureId($model);

        $template->set('arrPartialOpt', [
            'pictureId' => $pictureId,
            'moduleId' => $model->id,
            'apiParam' => $apiParam,
        ]);

        $template->set('csrfToken', $this->csrfTokenManager->getDefaultTokenValue());

        return $template->getResponse();
    }

    private function processFavoriteEventToggle(Request $request): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof FrontendUser) {
            return;
        }

        if (!$request->isMethod('POST')) {
            return;
        }

        if (!$request->request->has('eventId')) {
            return;
        }

        $this->handleFavoriteEventToggle($user, $request);
    }

    private function extractApiParameters(Request $request): array
    {
        $parameterKeys = [
            'limit',
            'calendarIds',
            'eventType',
            'suitableForBeginners',
            'publicTransportEvent',
            'favoredEvent',
            'organizers',
            'tourType',
            'courseType',
            'courseId',
            'getUpcoming',
            'dateStart',
            'dateEnd',
            'textSearch',
            'eventId',
            'arrIds',
            'username',
        ];

        $queryParams = $request->query->all();
        $apiParam = [];

        foreach ($parameterKeys as $key) {
            $apiParam[$key] = $this->getApiParam($key, $queryParams[$key] ?? null);
        }

        return $apiParam;
    }

    private function extractPictureId(ModuleModel $model): string
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->getContaoAdapter(StringUtil::class);

        $picture = $stringUtilAdapter->deserialize($model->imgSize, true);

        if (isset($picture[2]) && is_numeric($picture[2])) {
            return (string) $picture[2];
        }

        return '0';
    }

    private function getApiParam(string $key, mixed $value)
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->getContaoAdapter(StringUtil::class);

        switch ($key) {
            case 'organizers':
            case 'tourType':
            case 'courseType':
                if (!empty($value)) {
                    if (!\is_array($value)) {
                        // It can be transmitted like this: organizers=5,7 or organizers[]=5&amp;organizers[]=7
                        $value = explode(',', (string) $value);
                    }
                } else {
                    $value = [];
                }

                $value = array_unique(array_filter($value));
                $value = json_encode(array_map('intval', $value));

                break;

            case 'eventType':
                $value = $stringUtilAdapter->deserialize($this->model->eventType, true);
                $value = json_encode(array_unique($value));
                break;

            case 'limit':
                $value = $this->model->eventListLimitPerRequest;
                break;

            case 'calendarIds':
                if ($this->model->applyCalFilter) {
                    $calendarIds = $stringUtilAdapter->deserialize($this->model->cal_calendar, true);
                    $value = !empty($calendarIds) ? $calendarIds : [0];
                } else {
                    if (!\is_array($value)) {
                        // It can be transmitted like this: calendarIds=5,7 or
                        // calendarIds[]=5&amp;calendarIds[]=7
                        $value = explode(',', (string) $value);
                    }
                }

                $value = array_unique(array_filter($value));
                $value = json_encode(array_map('intval', $value));

                break;

            case 'favoredEvent':
                if ($this->model->showFavoredEventsOnly || '1' === $value) {
                    return '1';
                }

                return '';
        }

        return $value;
    }

    private function handleFavoriteEventToggle(FrontendUser $user, Request $request): void
    {
        $eventId = (int) $request->request->get('eventId');
        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_favored_events WHERE eventId = ? AND memberId = ?',
            [$eventId, $user->id],
            [Types::INTEGER, Types::INTEGER],
        );

        // Remove the event from the like-list
        if (false !== $id) {
            $this->connection->delete('tl_favored_events', ['id' => $id], [Types::INTEGER]);
            $json = ['status' => 'success', 'isFavoredEvent' => false];

            throw new ResponseException(new JsonResponse($json));
        }

        // Add the event to the like-list
        $set = [
            'memberId' => $user->id,
            'eventId' => $eventId,
            'tstamp' => time(),
        ];

        $types = [
            Types::INTEGER,
            Types::INTEGER,
            Types::INTEGER,
        ];

        $this->connection->insert('tl_favored_events', $set, $types);
        $json = ['status' => 'success', 'isFavoredEvent' => true];

        throw new ResponseException(new JsonResponse($json));
    }
}
