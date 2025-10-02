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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsFrontendModule(MemberDashboardUpcomingEventsController::TYPE, category: 'sac_event_tool_frontend_modules')]
class MemberDashboardUpcomingEventsController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_upcoming_events';

    private FrontendUser|null $user = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->user = $this->getUserFromToken();

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
            throw new UnauthorizedHttpException('Not authorized. Please log in as a frontend user.');
        }

        // Handle messages
        if (empty($this->user->email) || !$this->getContaoAdapter(Validator::class)->isEmail($this->user->email)) {
            $this->getContaoAdapter(Message::class)->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }

        // Add messages to the template
        $this->addMessagesToTemplate($template, $request);

        // Load language
        $this->getContaoAdapter(Controller::class)->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $template->set('arrUpcomingEvents', $this->fetchUpcomingEvents($model));

        return $template->getResponse();
    }

    private function getUserFromToken(): FrontendUser|null
    {
        $user = $this->tokenStorage
            ->getToken()
            ?->getUser()
        ;

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }

    private function fetchUpcomingEvents(ModuleModel $model): array
    {
        $arrUpcoming = $this->getContaoAdapter(CalendarEventsMemberModel::class)->findUpcomingEventsByMemberId($this->user->id);

        return array_map(
            function ($row) use ($model) {
                $row['deregistrationUrl'] = $this->generateDeregistrationUrl($row['eventRegistrationModel'], $model);

                return $row;
            },
            $arrUpcoming,
        );
    }

    private function generateDeregistrationUrl(CalendarEventsMemberModel $registrationModel, ModuleModel $moduleModel): string
    {
        $page = $this->getContaoAdapter(PageModel::class)->findById($moduleModel->eventDeregistrationPage);

        if (null === $page) {
            return '';
        }

        $query = \sprintf('regId=%d&callbackUrl=%s', $registrationModel->id, $this->requestStack->getCurrentRequest()->getUri());

        $url = $this->urlParser->addQueryString($query, $page->getFrontendUrl());

        return $this->uriSigner->sign($url);
    }

    /**
     * Add messages from session flash to the template.
     */
    private function addMessagesToTemplate(FragmentTemplate $template, Request $request): void
    {
        $messageAdapter = $this->getContaoAdapter(Message::class);

        $template->set('hasInfoMessage', false);
        $template->set('hasErrorMessage', false);

        if ($messageAdapter->hasInfo()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.info');
            $template->set('hasInfoMessage', true);
            $template->set('infoMessage', $session[0]);
        }

        if ($messageAdapter->hasError()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.error');
            $template->set('hasErrorMessage', true);
            $template->set('errorMessage', $session[0]);
            $template->set('errorMessages', $session);
        }

        $messageAdapter->reset();
    }
}
