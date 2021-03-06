<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EventListController.
 */
class EventListController extends AbstractFrontendModuleController
{
    /**
     * @var ModuleModel
     */
    protected $model;

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        $this->model = $model;

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices();
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        // Get filter params from request
        $arrKeys = ['limit', 'calendarIds', 'eventType', 'suitableForBeginners', 'organizers', 'tourType', 'courseType', 'courseId', 'year', 'dateStart', 'searchterm', 'eventId', 'courseId', 'arrIds', 'username'];

        $ApiParam = [];

        foreach ($arrKeys as $key) {
            $ApiParam[$key] = $this->getApiParam($key, $request->query->get($key));
        }

        // Get picture Id
        $arrPicture = $stringUtilAdapter->deserialize($model->imgSize, true);
        $pictureId = isset($arrPicture[2]) && is_numeric($arrPicture[2]) ? $arrPicture[2] : '0';

        $template->arrPartialOpt = [
            'pictureId' => $pictureId,
            'moduleId' => $model->id,
            'apiParam' => $ApiParam,
        ];

        return $template->getResponse();
    }

    private function getApiParam($strKey, $value = '')
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        switch ($strKey) {
            case 'organizers':

                if (!empty($value)) {
                    // The organizers GET param can be transmitted like this: organizers=5
                    if (\is_array($value)) {
                        $value = implode(',', $value);
                    } elseif (is_numeric($value)) {
                        $value = $value;
                    }
                    // Or the organizers GET param can be transmitted like this: organizers=5,7,3
                    elseif (strpos($value, ',')) {
                        $value = $value;
                    } else {
                        // Or the organizers GET param can be transmitted like this: organizers[]=5&organizers[]=7&organizers[]=3
                        $value = implode(',', $stringUtilAdapter->deserialize($value, true));
                    }
                }

                break;

            case 'eventType':
                $value = $stringUtilAdapter->deserialize($this->model->eventType, true);
                $value = array_map(
                    static function ($el) {
                        return '"'.$el.'"';
                    },
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
            case 'searchterm':
            case 'eventId':
            case 'courseId':
            case 'arrIds':
            case 'username':
                break;
        }

        return $value;
    }
}
