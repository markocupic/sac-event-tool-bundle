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

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(EventFilterFormController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_filter_form')]
class EventFilterFormController extends AbstractFrontendModuleController
{
	public const TYPE = 'event_filter_form';

	private array|null $arrAllowedFields = null;
	private PageModel|null $objPage = null;

	public function __construct(
		private readonly ContaoFramework $framework,
		private readonly TranslatorInterface $translator,
		private readonly UrlParser $urlParser,
		private readonly string $sacevtLocale,
	) {
	}

	public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
	{
		$this->objPage = $page;

		// Call the parent method
		return parent::__invoke($request, $model, $section, $classes);
	}

	protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
	{

		/** @var Controller $controllerAdapter */
		$controllerAdapter = $this->framework->getAdapter(Controller::class);

		/** @var Environment $environmentAdapter */
		$environmentAdapter = $this->framework->getAdapter(Environment::class);

		/** @var StringUtil $stringUtilAdapter */
		$stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

		$this->arrAllowedFields = $stringUtilAdapter->deserialize($model->eventFilterBoardFields, true);

		if ($environmentAdapter->get('isAjaxRequest')) {
			// Clean the url query from the param "dateStart" and redirect
			if ($request->query->get('year') > 0 && $request->query->has('dateStart')) {
				if ($request->query->get('year') !== date('Y', strtotime($request->query->get('dateStart')))) {
					$url = $this->urlParser->removeQueryString(['dateStart']);
					$controllerAdapter->redirect($url);
				}
			}

			// Clean the url query from the param "dateStart" and redirect
			if (!$request->query->has('year') && $request->query->has('dateStart')) {
				if (date('Y') !== date('Y', strtotime($request->query->get('dateStart')))) {
					$url = $this->urlParser->removeQueryString(['dateStart']);
					$controllerAdapter->redirect($url);
				}
			}
		}

		$template->set('fields', $this->arrAllowedFields);

		// Get the form
		$template->set('form', $this->generateForm($request));

		// Datepicker
		$template->set('sacevt_locale', $this->sacevtLocale);

		return $template->getResponse();
	}


	protected function generateForm(Request $request): Form
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
		$url = $this->objPage->getFrontendUrl();
		$objForm->setAction($url);

		$objForm->addFieldsFromDca(
			'tl_event_filter_form',
			function ($strField, $arrDca) {
				// Make sure to skip elements without an input type
				// otherwise we will run into an exception
				if (!isset($arrDca['inputType'])) {
					return false;
				}

				if (!\in_array($strField, $this->arrAllowedFields, true)) {
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
		if (!empty($this->arrAllowedFields) && \is_array($this->arrAllowedFields)) {
			foreach ($this->arrAllowedFields as $k) {
				if ($request->query->has($k)) {
					if ($objForm->hasFormField($k)) {
						$objWidget = $objForm->getWidget($k);

						if ('organizers' === $objWidget->name) {
							// As of Symfony 6, non-scalar values are no longer supported
							// we must use $request->query->all()['organizers']
							$organizers = $request->query->all()['organizers'] ?? [];

							// The organizers GET param can be transmitted like this:
							// organizers=5 or organizers[]=5&organizers[]=6 or organizers=5,6
							if (is_scalar($organizers)) {
								$organizers = [$organizers];
							}elseif (!empty($organizers) && str_contains($organizers, ',')) {
								$organizers = explode(',', $organizers);
							} elseif(is_array($organizers)) {
								// Do nothing if the value is an array
							}else{
								$organizers = $stringUtilAdapter->deserialize($organizers, true);
							}

							$objWidget->value = !empty($organizers) ? $organizers : '';
						} else {
							$objWidget->value = $request->query->all()[$k];
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
