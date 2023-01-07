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

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(EventFilterFormController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_event_filter_form')]
class EventFilterFormController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_filter_form';

    private ContaoFramework $framework;
    private TranslatorInterface $translator;
    private string $sacevtLocale;
    private array|null $arrAllowedFields = null;
    private PageModel|null $objPage = null;

    public function __construct(ContaoFramework $framework, TranslatorInterface $translator, string $sacevtLocale)
    {
        $this->framework = $framework;
        $this->translator = $translator;
        $this->sacevtLocale = $sacevtLocale;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->objPage = $page;

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $this->arrAllowedFields = $stringUtilAdapter->deserialize($model->eventFilterBoardFields, true);

        if ($environmentAdapter->get('isAjaxRequest')) {
            // Clean url dateStart url param & redirect
            if ($inputAdapter->get('year') > 0 && '' !== $inputAdapter->get('dateStart')) {
                if ($inputAdapter->get('year') !== $dateAdapter->parse('Y', strtotime($inputAdapter->get('dateStart')))) {
                    $url = $urlAdapter->removeQueryString(['dateStart']);
                    $controllerAdapter->redirect($url);
                }
            }
            // Clean url dateStart url param & redirect
            if (empty($inputAdapter->get('year')) && !empty($inputAdapter->get('dateStart'))) {
                if ($dateAdapter->parse('Y') !== $dateAdapter->parse('Y', strtotime($inputAdapter->get('dateStart')))) {
                    $url = $urlAdapter->removeQueryString(['dateStart']);
                    $controllerAdapter->redirect($url);
                }
            }
        }

        $template->fields = $this->arrAllowedFields;

        // Get the form
        $template->form = $this->generateForm();

        // Datepicker
        $template->sacevt_locale = $this->sacevtLocale;

        return $template->getResponse();
    }

    /**
     * Generate filter form.
     */
    protected function generateForm(): Form
    {
        // Set adapters
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile('tl_event_filter_form');

        // Generate form
        $objForm = new Form(
            'event-filter-board-form',
            'GET',
            function () {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->framework->getAdapter(Input::class);

                return 'eventFilter' === $inputAdapter->get('eventFilter');
            }
        );

        // Action
        $url = $this->objPage->getFrontendUrl();
        $objForm->setFormActionFromUri($url);

        $objForm->addFieldsFromDca(
            'tl_event_filter_form',
            function (&$strField, &$arrDca) {
                // Make sure to skip elements without inputType otherwise this will throw an exception
                if (!isset($arrDca['inputType'])) {
                    return false;
                }

                if (!\in_array($strField, $this->arrAllowedFields, true)) {
                    return false;
                }

                // You must return true otherwise the field will be skipped
                return true;
            }
        );

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => $this->translator->trans('tl_event_filter_form.submitBtn', [], 'contao_default'),
            'inputType' => 'submit',
        ]);

        // Set form field value from $_GET
        if (isset($_GET) && !empty($this->arrAllowedFields) && \is_array($this->arrAllowedFields)) {
            foreach ($this->arrAllowedFields as $k) {
                if ('' !== $inputAdapter->get($k)) {
                    if ($objForm->hasFormField($k)) {
                        $objWidget = $objForm->getWidget($k);

                        if ('organizers' === $objWidget->name) {
                            // The organizers GET param can be transmitted like this: organizers=5
                            if (\is_array($inputAdapter->get('organizers'))) {
                                $arrOrganizers = $inputAdapter->get('organizers');
                            } elseif (is_numeric($inputAdapter->get('organizers'))) {
                                $arrOrganizers = [$inputAdapter->get('organizers')];
                            }
                            // Or the organizers GET param can be transmitted like this: organizers=5,7,3
                            elseif (!empty($inputAdapter->get('organizers')) && strpos($inputAdapter->get('organizers'), ',', 1)) {
                                $arrOrganizers = explode(',', $inputAdapter->get('organizers'));
                            } else {
                                // Or the organizers GET param can be transmitted like this: organizers[]=5&organizers[]=7&organizers[]=3
                                $arrOrganizers = $stringUtilAdapter->deserialize($inputAdapter->get('organizers'), true);
                            }

                            $objWidget->value = !empty($arrOrganizers) ? $arrOrganizers : '';
                        } else {
                            $objWidget->value = $inputAdapter->get($k);
                        }
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

        return $objForm;
    }
}
