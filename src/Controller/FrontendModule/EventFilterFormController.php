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

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(EventFilterFormController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_filter_form')]
class EventFilterFormController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_filter_form';
    public const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly string $sacevtLocale,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Do not allow an invalid "year" param.
        if ($request->query->has('year')) {
            $validYears = implode('|', range(2016, (int) date('Y') + 1));

            // Check if year number is valid.
            if (!preg_match('/^('.$validYears.')$/', $request->query->get('year'))) {
                $url = $this->urlParser->removeQueryString(['year']);

                // Reload the page with the fixed url
                $controllerAdapter->redirect($url);
            }

            // We assume that the "year" param is valid.
            // Add a correct "dateStart" param if there is none.
            if (!$this->isDateValid($request->query->get('dateStart', ''))) {
                $year = $request->query->get('year');
                $date = $year.'-01-01';
                $url = $this->urlParser->removeQueryString(['dateStart']);
                $url = $this->urlParser->addQueryString('dateStart='.$date, $url);

                // Reload the page with the fixed url
                $controllerAdapter->redirect($url);
            }
        }

        // Clean the url query from an invalid param "dateStart".
        if ($request->query->has('dateStart')) {
            if (!$this->isDateValid($request->query->get('dateStart'), self::DATE_FORMAT)) {
                $url = $this->urlParser->removeQueryString(['dateStart']);

                // Reload the page with the fixed url
                $controllerAdapter->redirect($url);
            }

            if ($request->query->get('year') !== date('Y', strtotime($request->query->get('dateStart')))) {
                $yearNumber = date('Y', strtotime($request->query->get('dateStart')));
                $url = $this->urlParser->removeQueryString(['year']);
                $url = $this->urlParser->addQueryString('year='.$yearNumber, $url);

                // Reload the page with the fixed url
                $controllerAdapter->redirect($url);
            }
        }

        $arrAllowedFields = $stringUtilAdapter->deserialize($model->eventFilterBoardFields, true);

        $template->set('fields', $arrAllowedFields);
        $template->set('form', $this->generateForm($request, $arrAllowedFields));

        // Datepicker config
        $template->set('sacevt_locale', $this->sacevtLocale);
        $template->set('date_format', self::DATE_FORMAT);

        return $template->getResponse();
    }

    protected function generateForm(Request $request, array $arrAllowedFields): Form
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile('tl_event_filter_form');

        // Generate form
        $objForm = new Form(
            'event-filter-board-form',
            Request::METHOD_GET,
        );

        // Set the action attribute
        $objPage = $request->attributes->get('pageModel');
        $url = $objPage->getFrontendUrl();
        $objForm->setAction($url);

        $objForm->addFieldsFromDca(
            'tl_event_filter_form',
            static function ($strField, $arrDca) use ($arrAllowedFields) {
                // Make sure to skip elements without an input type
                // otherwise we will run into an exception
                if (!isset($arrDca['inputType'])) {
                    return false;
                }

                if (!\in_array($strField, $arrAllowedFields, true)) {
                    return false;
                }

                // You must return true
                // otherwise the field will be skipped
                return true;
            }
        );

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => $this->translator->trans('tl_event_filter_form.submitBtn', [], 'contao_default'),
            'inputType' => 'submit',
        ]);

        // Set form field value from $_GET
        if (!empty($arrAllowedFields)) {
            foreach ($arrAllowedFields as $k) {
                if ($request->query->has($k)) {
                    if (!$objForm->hasFormField($k)) {
                        continue;
                    }
                    $objWidget = $objForm->getWidget($k);
                    $arrMultiSelects = ['organizers', 'tourType', 'courseType'];
                    // Multi selects
                    if (\in_array($k, $arrMultiSelects, true)) {
                        // As of Symfony 6, non-scalar values are no longer supported
                        // we must use $request->query->all()[$k]
                        $value = $request->query->all()[$k] ?? [];

                        // e.g the organizers GET param can be transmitted like this:
                        // organizers=5 or organizers[]=5&organizers[]=6 or organizers=5,6
                        if (\is_scalar($value)) {
                            $value = [$value];
                        } elseif (\is_array($value)) {
                            // Do nothing if the value is an array
                        } elseif (\is_string($value) && !empty($value) && str_contains($value, ',')) {
                            $value = explode(',', $value);
                        } else {
                            $value = $stringUtilAdapter->deserialize($value, true);
                        }

                        $objWidget->value = !empty($value) ? $value : '';
                    } else {
                        $objWidget->value = $request->query->all()[$k];
                    }
                }
            }
        }

        if ($objForm->hasFormField('suitableForBeginners')) {
            $objForm->getWidget('suitableForBeginners')->template = 'form_bs_switch';
        }

        if ($objForm->hasFormField('publicTransportEvent')) {
            $objForm->getWidget('publicTransportEvent')->template = 'form_bs_switch';
        }

        if ($objForm->hasFormField('favoredEvent')) {
            $objForm->getWidget('favoredEvent')->template = 'form_bs_switch';
        }

        return $objForm;
    }

    protected function isDateValid(string $date, string $format = 'Y-m-d'): bool
    {
        $objDate = \DateTime::createFromFormat($format, $date);

        return $objDate && $objDate->format($format) === $date;
    }
}
