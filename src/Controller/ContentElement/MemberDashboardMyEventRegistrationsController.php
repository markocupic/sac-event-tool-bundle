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

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
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
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsContentElement(MemberDashboardMyEventRegistrationsController::TYPE, category: 'sac_event_tool_content_elements')]
class MemberDashboardMyEventRegistrationsController extends AbstractContentElementController
{
    public const string TYPE = 'member_dashboard_my_event_registrations';

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
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
            $this->getContaoAdapter(Message::class)->addInfo('Leider wurde für dieses Konto in der Datenbank keine gültige E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }

        // Add messages to the template
        $this->addMessagesToTemplate($template, $request);

        // Load language
        $this->getContaoAdapter(Controller::class)->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $template->set('upcomingEvents', $this->buildItems($this->fetchUpcomingEvents($user), $model, $request, true));

        // Past events
        $template->set('pastEvents', $this->buildItems($this->fetchPastEvents($user, 15), $model, $request, false));

        return $template->getResponse();
    }

    private function fetchUpcomingEvents(FrontendUser $user): array
    {
        $qb = $this->connection->createQueryBuilder();

        return $qb
            ->select('e.id AS eventId', 'm.id AS regId')
            ->from('tl_calendar_events_member', 'm')
            ->innerJoin('m', 'tl_calendar_events', 'e', 'e.id = m.eventId')
            ->where('m.sacMemberId = :sacMemberId')
            ->andWhere('e.published = 1')
            ->andWhere('e.endDate >= :endDate')
            ->setParameter('sacMemberId', $user->sacMemberId)
            ->setParameter('endDate', strtotime('today midnight'))
            ->orderBy('e.startDate', 'ASC')
            ->fetchAllAssociative()
        ;
    }

    private function fetchPastEvents(FrontendUser $user, int $limit = 10): array
    {
        $qb = $this->connection->createQueryBuilder();

        return $qb
            ->select('e.id AS eventId', 'm.id AS regId')
            ->from('tl_calendar_events_member', 'm')
            ->innerJoin('m', 'tl_calendar_events', 'e', 'e.id = m.eventId')
            ->where('m.sacMemberId = :sacMemberId')
            ->andWhere('e.published = 1')
            ->andWhere('e.endDate < :endDate')
            ->setParameter('sacMemberId', $user->sacMemberId)
            ->setParameter('endDate', strtotime('today midnight'))
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit)
            ->fetchAllAssociative()
        ;
    }

    private function buildItems(array $rows, ContentModel $model, Request $request, bool $withUnsubscribeUrl): array
    {
        $items = [];

        $unsubscribePageUrl = $withUnsubscribeUrl ? $this->buildUnsubscribePageUrl($model) : null;

        foreach ($rows as $row) {
            $item = [];
            $eventModel = $this->getContaoAdapter(CalendarEventsModel::class)->findById($row['eventId']);
            $regModel = $this->getContaoAdapter(CalendarEventsMemberModel::class)->findById($row['regId']);

            if (null === $eventModel || null === $regModel) {
                continue;
            }

            $item['event'] = $eventModel->row();
            $item['event']['eventUrl'] = $this->contentUrlGenerator->generate($eventModel, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $item['event']['dateSpan'] = $this->calendarEventsUtil->getEventPeriod($eventModel, 'd.m.Y');

            $item['registration'] = $regModel->row();
            $item['registration']['unsubscribeUrl'] = null !== $unsubscribePageUrl ? $this->buildUnsubscribeUrl($regModel, $unsubscribePageUrl, $request) : null;

            $items[] = $item;
        }

        return $items;
    }

    private function buildUnsubscribePageUrl(ContentModel $model): string|null
    {
        $page = $this->getContaoAdapter(PageModel::class)->findById($model->eventUnsubscribePage);

        if (null === $page) {
            return null;
        }

        return $this->contentUrlGenerator->generate($page, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function buildUnsubscribeUrl(CalendarEventsMemberModel $registrationModel, string $unsubscribePageUrl, Request $request): string
    {
        $query = http_build_query([
            'regId' => $registrationModel->id,
            'callbackUrl' => $request->getUri(),
        ]);

        $url = $this->urlParser->addQueryString($query, $unsubscribePageUrl);

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
