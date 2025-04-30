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

use Codefog\HasteBundle\UrlParser;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[AsFrontendModule(MemberDashboardUpcomingEventsController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_member_dashboard_upcoming_events')]
class MemberDashboardUpcomingEventsController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_upcoming_events';

    private FrontendUser|null $user = null;
    private FragmentTemplate|null $template = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        // Set adapters
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Handle messages
        if (empty($this->user->email) || !$validatorAdapter->isEmail($this->user->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Add messages to template
        $this->addMessagesToTemplate($request);

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $arrUpcoming = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($this->user->id);
        $arrUpcoming = array_map(
            function ($row) use ($model) {
                $row['deregistrationUrl'] = $this->generateDeregistrationUrl($row['eventRegistrationModel'], $model);

                return $row;
            },
            $arrUpcoming
        );
        $this->template->set('arrUpcomingEvents', $arrUpcoming);

        return $this->template->getResponse();
    }

    private function generateDeregistrationUrl(CalendarEventsMemberModel $registrationModel, ModuleModel $moduleModel): string
    {
        $objPage = $this->framework->getAdapter(PageModel::class)->findByPk($moduleModel->eventDeregistrationPage);

        if (null === $objPage) {
            return '';
        }

        $queryString = sprintf('regId=%d&callbackUrl=%s', $registrationModel->id, $this->requestStack->getCurrentRequest()->getUri());

        $url = $this->urlParser->addQueryString($queryString, $objPage->getFrontendUrl());

        return $this->uriSigner->sign($url);
    }

    /**
     * Add messages from session flash to template.
     */
    private function addMessagesToTemplate(Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $this->template->set('hasInfoMessage', false);
        $this->template->set('hasErrorMessage', false);

        if ($messageAdapter->hasInfo()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.info');
            $this->template->set('hasInfoMessage', true);
            $this->template->set('infoMessage', $session[0]);
        }

        if ($messageAdapter->hasError()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.error');
            $this->template->set('hasErrorMessage', true);
            $this->template->set('errorMessage', $session[0]);
            $this->template->set('errorMessages', $session);
        }

        $messageAdapter->reset();
    }
}
