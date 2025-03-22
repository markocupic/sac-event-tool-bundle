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
    private const MIN_YEAR = 2017;
    private int $urlFixCount = 0;

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

        $url = $this->sanitizeUrl($request);

        if ($this->urlFixCount) {
            $controllerAdapter->redirect($url);
        }

        $arrAllowedFields = StringUtil::deserialize($model->eventFilterBoardFields, true);

        $template->set('fields', $arrAllowedFields);
        $template->set('form', $this->generateForm($request, $arrAllowedFields));

        // Datepicker config
        $template->set('sacevt_locale', $this->sacevtLocale);
        $template->set('date_format', self::DATE_FORMAT);

        return $template->getResponse();
    }

    protected function generateForm(Request $request, array $arrAllowedFields): Form
    {
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
                if (!$request->query->has($k)) {
                    continue;
                }

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
                        $value = StringUtil::deserialize($value, true);
                    }

                    $objWidget->value = !empty($value) ? $value : '';
                } else {
                    $objWidget->value = $request->query->all()[$k];
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

    protected function isDateValid(string $dateString, string $format = 'Y-m-d'): bool
    {
        if ('' === $dateString) {
            return false;
        }

        $parsedDate = \DateTime::createFromFormat($format, $dateString);

        return false !== $parsedDate && $parsedDate->format($format) === $dateString;
    }

    protected function isYearWithinValidRange(string $year): bool
    {
        $validYearRange = implode('|', range(self::MIN_YEAR, (int) date('Y') + 1));

        return 1 === preg_match(
            '/^('.$validYearRange.')$/',
            $year
        );
    }

    protected function sortUrlParams(string $url): string
    {
        // Parse the URL
        $parsedUrl = parse_url($url);

        if (empty($parsedUrl['query'])) {
            // Return the original URL if there is no query string to sort
            return $url;
        }

        // Extract and sort query parameters
        parse_str($parsedUrl['query'], $queryParams);
        ksort($queryParams);

        // Remove empty params
        $queryParams = array_filter($queryParams);

        // Build the new query string
        $sortedQueryString = http_build_query($queryParams);

        // Reconstruct the URL with the sorted query string
        // Rebuild the URL from its parts and the sorted query string
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '';

        return sprintf('%s://%s%s?%s', $scheme, $host, $path, $sortedQueryString);
    }

    /**
     * Sanitizes the given request URL by validating and adjusting query parameters.
     *
     * The method ensures that parameters conform to expected rules, such as:
     * - Adding a default 'getUpcoming=1' parameter if relevant parameters are missing.
     * - Restricting the 'getUpcoming' parameter to the value '1' only.
     * - Disallowing 'dateStart', 'dateEnd', and 'year' parameters when 'getUpcoming=1'.
     * - Validating the 'year' parameter as a valid year number.
     * - Validating 'dateStart' and 'dateEnd' parameters to ensure they follow the YYYY-MM-DD format.
     * - Adjusting 'dateStart' and 'dateEnd' parameters to match the provided 'year' parameter, if required.
     * - Ensuring 'dateStart' does not come after 'dateEnd'.
     * - Aligning 'dateStart' and 'dateEnd' parameters to be within the same year.
     * - Automatically appending a missing 'dateEnd' or 'dateStart' parameter and adjusting 'year' accordingly.
     *
     * The resulting URL parameters are sorted and sanitized. Fixes are tracked via a counter.
     *
     * @param Request $request the incoming HTTP request containing URL query parameters
     *
     * @return string the sanitized and sorted URL with adjusted query parameters
     */
    protected function sanitizeUrl(Request $request): string
    {
        $url = $request->getUri();
        $arrAll = $request->query->all();

        // Apply default parameter
        if (!isset($arrAll['getUpcoming']) && !isset($arrAll['year']) && !isset($arrAll['dateStart']) && !isset($arrAll['dateEnd'])) {
            $arrAll['getUpcoming'] = '1';
            $url = $this->urlParser->addQueryString('getUpcoming=1', $url);

            ++$this->urlFixCount;
        }

        // The only valid value for the getUpcoming param is '1'
        if (isset($arrAll['getUpcoming']) && '1' !== $arrAll['getUpcoming']) {
            unset($arrAll['getUpcoming']);
            $url = $this->urlParser->removeQueryString(['getUpcoming'], $url);

            ++$this->urlFixCount;
        }

        // Do not allow 'dateStart', 'dateEnd' or 'year' if 'getUpcoming' === '1'
        if (isset($arrAll['getUpcoming']) && '1' === $arrAll['getUpcoming']) {
            foreach (['dateStart', 'dateEnd', 'year'] as $param) {
                if (isset($arrAll[$param])) {
                    unset($arrAll[$param]);
                    $url = $this->urlParser->removeQueryString([$param], $url);

                    ++$this->urlFixCount;
                }
            }
        }

        // Validate the year param
        if (isset($arrAll['year']) && !$this->isYearWithinValidRange($arrAll['year'])) {
            unset($arrAll['year']);
            $url = $this->urlParser->removeQueryString(['year'], $url);

            ++$this->urlFixCount;
        }

        // Validate if the dateStart param  is in format YYYY-MM-DD
        if (isset($arrAll['dateStart']) && !$this->isDateValid($arrAll['dateStart'])) {
            unset($arrAll['dateStart']);
            $url = $this->urlParser->removeQueryString(['dateStart'], $url);

            ++$this->urlFixCount;
        }

        // Validate if the dateEnd param is in format YYYY-MM-DD
        if (isset($arrAll['dateEnd']) && !$this->isDateValid($arrAll['dateEnd'])) {
            unset($arrAll['dateEnd']);
            $url = $this->urlParser->removeQueryString(['dateEnd'], $url);

            ++$this->urlFixCount;
        }

        // Replace the first 4 digits (year) in dateStart with the year param
        // if the year number in dateStart does not match the year.
        if (!empty($arrAll['year']) && !empty($arrAll['dateStart'])) {
            $newDate = $arrAll['year'].substr($arrAll['dateStart'], 4);

            if ($arrAll['dateStart'] !== $newDate) {
                $arrAll['dateStart'] = $newDate;
                $url = $this->urlParser->removeQueryString(['dateStart'], $url);
                $url = $this->urlParser->addQueryString('dateStart='.$arrAll['dateStart'], $url);

                ++$this->urlFixCount;
            }
        }

        // Replace the first 4 digits (year) in dateEnd with the year param
        // if the year number in dateEnd does not match the year.
        if (!empty($arrAll['year']) && !empty($arrAll['dateEnd'])) {
            $newDate = $arrAll['year'].substr($arrAll['dateEnd'], 4);

            if ($arrAll['dateEnd'] !== $newDate) {
                $arrAll['dateEnd'] = $newDate;
                $url = $this->urlParser->removeQueryString(['dateEnd'], $url);
                $url = $this->urlParser->addQueryString('dateEnd='.$arrAll['dateEnd'], $url);

                ++$this->urlFixCount;
            }
        }

        // dateStart should not follow dateEnd
        if (!empty($arrAll['dateStart']) && !empty($arrAll['dateEnd'])) {
            $timeStart = strtotime($arrAll['dateStart']);
            $timeEnd = strtotime($arrAll['dateEnd']);

            if ($timeStart && $timeEnd && $timeStart > $timeEnd) {
                $arrAll['dateEnd'] = date('Y', $timeEnd).'-12-31';
                $url = $this->urlParser->removeQueryString(['dateEnd'], $url);
                $url = $this->urlParser->addQueryString('dateEnd='.$arrAll['dateEnd'], $url);

                ++$this->urlFixCount;
            }
        }

        // The end date must be in the same year as the start date.
        if (isset($arrAll['dateEnd'], $arrAll['dateStart'])) {
            $yearEnd = substr($arrAll['dateEnd'], 0, 4);
            $yearStart = substr($arrAll['dateStart'], 0, 4);

            if ($yearStart !== $yearEnd) {
                $arrAll['dateEnd'] = $yearStart.'-12-31';
                $url = $this->urlParser->removeQueryString(['dateEnd', 'year'], $url);
                $url = $this->urlParser->addQueryString('dateEnd='.$arrAll['dateEnd'], $url);
                $url = $this->urlParser->addQueryString('year='.$yearStart, $url);

                ++$this->urlFixCount;
            }
        }

        // dateStart without dateEnd is not allowed
        if (isset($arrAll['dateStart']) && !isset($arrAll['dateEnd'])) {
            $arrAll['dateEnd'] = substr($arrAll['dateStart'], 0, 4).'-12-31';
            $arrAll['year'] = substr($arrAll['dateStart'], 0, 4);
            $url = $this->urlParser->removeQueryString(['dateEnd', 'year'], $url);
            $url = $this->urlParser->addQueryString('dateEnd='.$arrAll['dateEnd'], $url);
            $url = $this->urlParser->addQueryString('year='.$arrAll['year'], $url);

            ++$this->urlFixCount;
        }

        // dateEnd without dateStart is not allowed
        if (isset($arrAll['dateEnd']) && !isset($arrAll['dateStart'])) {
            $arrAll['dateStart'] = substr($arrAll['dateEnd'], 0, 4).'-01-01';
            $arrAll['year'] = substr($arrAll['dateEnd'], 0, 4);
            $url = $this->urlParser->removeQueryString(['dateStart', 'year'], $url);
            $url = $this->urlParser->addQueryString('dateStart='.$arrAll['dateStart'], $url);
            $url = $this->urlParser->addQueryString('year='.$arrAll['year'], $url);

            ++$this->urlFixCount;
        }

        $sortedUrl = $this->sortUrlParams($url);

        if (urldecode($sortedUrl) !== urldecode($url)) {
            $url = $sortedUrl;

            ++$this->urlFixCount;
        }

        return $url;
    }
}
