<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(EventListController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_list')]
class EventListController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_list';
    protected ModuleModel|null $model = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->model = $model;

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser && $request->isMethod('POST') && $request->request->has('eventId')) {
            $eventId = (int) $request->request->get('eventId');
            $id = $this->connection->fetchOne(
                'SELECT id FROM tl_favored_events WHERE eventId = ? AND memberId = ?',
                [$eventId, $user->id],
                [Types::INTEGER, Types::INTEGER],
            );

            if (false !== $id) {
                $this->connection->delete('tl_favored_events', ['id' => $id], [Types::INTEGER]);
                $json = ['status' => 'success', 'isFavoredEvent' => false];

                throw new ResponseException(new JsonResponse($json));
            }
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

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Get filter params from request
        $arrKeys = [
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
            'year',
            'dateStart',
            'textSearch',
            'eventId',
            'courseId',
            'arrIds',
            'username',
        ];

        $apiParam = [];

        $requestQueryAll = $request->query->all();

        foreach ($arrKeys as $key) {
            $apiParam[$key] = $this->getApiParam($key, $requestQueryAll[$key] ?? null);
        }

        // Get picture id
        $arrPicture = $stringUtilAdapter->deserialize($model->imgSize, true);
        $pictureId = isset($arrPicture[2]) && is_numeric($arrPicture[2]) ? $arrPicture[2] : '0';

        $template->set('arrPartialOpt', [
            'pictureId' => $pictureId,
            'moduleId' => $model->id,
            'apiParam' => $apiParam,
        ]);

        // Add CSRF token to the template
        $template->set('csrfToken', $this->csrfTokenManager->getDefaultTokenValue());

        return $template->getResponse();
    }

    private function getApiParam(string $strKey, mixed $value)
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        switch ($strKey) {
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
                    $arrCalIds = $stringUtilAdapter->deserialize($this->model->cal_calendar, true);
                    $value = !empty($arrCalIds) ? $arrCalIds : [0];
                } else {
                    if (!\is_array($value)) {
                        // It can be transmitted like this: calendarIds=5,7 or calendarIds[]=5&amp;calendarIds[]=7
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
}
