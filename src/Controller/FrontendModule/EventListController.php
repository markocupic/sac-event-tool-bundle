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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(EventListController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_event_list')]
class EventListController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_list';
    protected ModuleModel|null $model = null;

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->model = $model;

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Get filter params from request
        $arrKeys = [
            'limit',
            'calendarIds',
            'eventType',
            'suitableForBeginners',
            'publicTransportEvent',
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

        $ApiParam = [];

        foreach ($arrKeys as $key) {
            $ApiParam[$key] = $this->getApiParam($key, $request->query->get($key));
        }

        // Get picture id
        $arrPicture = $stringUtilAdapter->deserialize($model->imgSize, true);
        $pictureId = isset($arrPicture[2]) && is_numeric($arrPicture[2]) ? $arrPicture[2] : '0';

        $template->arrPartialOpt = [
            'pictureId' => $pictureId,
            'moduleId' => $model->id,
            'apiParam' => $ApiParam,
        ];

        return $template->getResponse();
    }

    private function getApiParam(string $strKey, mixed $value)
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        switch ($strKey) {
            case 'organizers':

                if (!empty($value)) {
                    // The organizers GET param can be transmitted like this: organizers=5
                    if (\is_array($value)) {
                        $value = implode(',', $value);
                    } elseif (is_numeric($value)) {
                        // Do nothing
                    }
                    // Or the organizers GET param can be transmitted like this: organizers=5,7,3
                    elseif (str_contains($value, ',')) {
                        // Do nothing
                    } else {
                        // Or the organizers GET param can be transmitted like this: organizers[]=5&organizers[]=7&organizers[]=3
                        $value = implode(',', $stringUtilAdapter->deserialize($value, true));
                    }
                }

                break;

            case 'eventType':
                $value = $stringUtilAdapter->deserialize($this->model->eventType, true);
                $value = array_map(
                    static fn ($el) => '"'.$el.'"',
                    $value
                );
                $value = implode(',', $value);
                break;

            case 'limit':
                $value = $this->model->eventListLimitPerRequest;
                break;

            case 'calendarIds':
                $value = implode(',', $stringUtilAdapter->deserialize($this->model->cal_calendar, true));
                break;

            case 'tourType':
            case 'courseType':
            case 'courseId':
            case 'year':
            case 'dateStart':
            case 'textSearch':
            case 'eventId':
            case 'suitableForBeginners':
            case 'publicTransportEvent':
            case 'arrIds':
            case 'username':
                break;
        }

        return $value;
    }
}
