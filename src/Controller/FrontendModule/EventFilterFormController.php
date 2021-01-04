<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Haste\Form\Form;
use Haste\Util\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class EventFilterFormController
 * @package Markocupic\SacEventToolBundle\Controller\EventEventFilterFormController
 * @FrontendModule("event_filter_form", category="sac_event_tool_frontend_modules")
 */
class EventFilterFormController extends AbstractFrontendModuleController
{

    /**
     * @var array
     */
    protected $arrAllowedFields;

    /**
     * @var PageModel
     */
    protected $objPage;

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        $this->objPage = $page;

        // Call the parent method
         return parent::__invoke($request, $model, $section, $classes, $page);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        $this->arrAllowedFields = $stringUtilAdapter->deserialize($model->eventFilterBoardFields, true);

        if ($environmentAdapter->get('isAjaxRequest'))
        {
            // Clean url dateStart url param & redirect
            if ($inputAdapter->get('year') > 0 && $inputAdapter->get('dateStart') != '')
            {
                if ($inputAdapter->get('year') != $dateAdapter->parse('Y', strtotime($inputAdapter->get('dateStart'))))
                {
                    $url = $urlAdapter->removeQueryString(['dateStart']);
                    $controllerAdapter->redirect($url);
                }
            }
            // Clean url dateStart url param & redirect
            if ($inputAdapter->get('year') == '' && $inputAdapter->get('dateStart') != '')
            {
                if ($dateAdapter->parse('Y') != $dateAdapter->parse('Y', strtotime($inputAdapter->get('dateStart'))))
                {
                    $url = $urlAdapter->removeQueryString(['dateStart']);
                    $controllerAdapter->redirect($url);
                }
            }
        }

        $template->fields = $this->arrAllowedFields;
        $this->generateForm($template);

        return $template->getResponse();
    }

    /**
     * Generate filter form
     * @param Template $template
     */
    protected function generateForm(Template $template): void
    {
        // Set adapters
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile('tl_event_filter_form');

        /** @var Translator $translator */
        $translator = $this->get('translator');

        // Generate form
        $objForm = new Form('event-filter-board-form', 'GET', function () {
            /** @var Input $inputAdapter */
            $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

            return $inputAdapter->get('eventFilter') === 'eventFilter';
        });

        // Action
        $url = $this->objPage->getFrontendUrl();
        $objForm->setFormActionFromUri($url);

        $objForm->addFieldsFromDca('tl_event_filter_form', function (&$strField, &$arrDca) {
            // Make sure to skip elements without inputType or you will get an exception
            if (!isset($arrDca['inputType']))
            {
                return false;
            }

            if (!in_array($strField, $this->arrAllowedFields))
            {
                return false;
            }

            // You must return true otherwise the field will be skipped
            return true;
        });

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $translator->trans('tl_event_filter_form.submitBtn', [], 'contao_default'),
            'inputType' => 'submit'
        ));

        // Set form field value from $_GET
        if (isset($_GET) && is_array($this->arrAllowedFields) && !empty($this->arrAllowedFields))
        {
            foreach ($this->arrAllowedFields as $k)
            {
                if ($inputAdapter->get($k) != '')
                {
                    if ($objForm->hasFormField($k))
                    {
                        $objWidget = $objForm->getWidget($k);
                        if ($objWidget->name === 'organizers')
                        {
                            // The organizers GET param can be transmitted like this: organizers=5
                            if (is_array($inputAdapter->get('organizers')))
                            {
                                $arrOrganizers = $inputAdapter->get('organizers');
                            }
                            elseif (is_numeric($inputAdapter->get('organizers')))
                            {
                                $arrOrganizers = [$inputAdapter->get('organizers')];
                            }
                            // Or the organizers GET param can be transmitted like this: organizers=5,7,3
                            elseif (strpos($inputAdapter->get('organizers'), ',', 1))
                            {
                                $arrOrganizers = explode(',', $inputAdapter->get('organizers'));
                            }
                            else
                            {
                                // Or the organizers GET param can be transmitted like this: organizers[]=5&organizers[]=7&organizers[]=3
                                $arrOrganizers = $stringUtilAdapter->deserialize($inputAdapter->get('organizers'), true);
                            }

                            $objWidget->value = !empty($arrOrganizers) ? $arrOrganizers : '';
                        }
                        else
                        {
                            $objWidget->value = $inputAdapter->get($k);
                        }
                    }
                }
            }
        }

        $template->form = $objForm;
    }
}
