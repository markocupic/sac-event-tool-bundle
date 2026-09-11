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

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\Message;
use Contao\PageModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsContentElement(MemberDashboardMyEventsController::TYPE, category: 'sac_event_tool_content_elements')]
class MemberDashboardMyEventsController extends AbstractContentElementController
{
    public const string TYPE = 'member_dashboard_my_events';

    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly Security $security,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array|null $classes = null, PageModel|null $pageModel = null): Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $user = $this->security->getUser();

        // Do not allow for not authorized users
        if (!$user instanceof FrontendUser) {
            throw new AccessDeniedException('Not authorized. Please log in as a frontend user.');
        }

        // Handle messages
        if (empty($user->email) || !$this->getContaoAdapter(Validator::class)->isEmail($user->email)) {
            $this->getContaoAdapter(Message::class)->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }

        // Add messages to the template
        $this->addMessagesToTemplate($template, $request);

        // Load language
        $this->getContaoAdapter(Controller::class)->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $template->set('arrUpcomingEvents', $this->fetchUpcomingEvents($user, $model, $request));

        return $template->getResponse();
    }

    private function fetchUpcomingEvents(FrontendUser $user, ContentModel $model, Request $request): array
    {
        $arrUpcoming = $this->getContaoAdapter(CalendarEventsMemberModel::class)->findUpcomingEventsByMemberId($user->id);

        // Resolve the unsubscribe-page only once for all rows
        $unsubscribeUrl = $this->getUnsubscribePageUrl($model);

        return array_map(
            function (array $row) use ($unsubscribeUrl, $request): array {
                $row['unsubscribeUrl'] = $this->generateUnsubscribeUrl($row['eventRegistrationModel'], $unsubscribeUrl, $request);

                return $row;
            },
            $arrUpcoming,
        );
    }

    private function getUnsubscribePageUrl(ContentModel $model): string|null
    {
        $page = $this->getContaoAdapter(PageModel::class)->findById($model->eventUnsubscribePage);

        if (null === $page) {
            return null;
        }

        return $this->contentUrlGenerator->generate($page, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateUnsubscribeUrl(CalendarEventsMemberModel $registrationModel, string|null $unsubscribePageUrl, Request $request): string
    {
        if (null === $unsubscribePageUrl) {
            return '';
        }

        $query = http_build_query([
            'regId' => $registrationModel->id,
            'callbackUrl' => $request->getUri(),
        ]);

        $url = $unsubscribePageUrl.(str_contains($unsubscribePageUrl, '?') ? '&' : '?').$query;

        return $this->uriSigner->sign($url);
    }

    /**
     * Add messages from session flash to the template.
     */
    private function addMessagesToTemplate(FragmentTemplate $template, Request $request): void
    {
        /** @var Adapter<Message> $messageAdapter */
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
