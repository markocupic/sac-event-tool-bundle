<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Ajax;

use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Haste\Util\Url;
use NotificationCenter\Model\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class MemberDashboardWriteEventArticleController.
 */
class MemberDashboardWriteEventArticleController extends AbstractController
{
    private ContaoFramework $framework;

    private Connection $connection;

    private CsrfTokenManagerInterface $tokenManager;

    private RequestStack $requestStack;

    private Security $security;

    private TranslatorInterface $translator;

    private string $projectDir;

    private string $tokenName;

    /**
     * MemberDashboardWriteEventArticleController constructor.
     * Handles ajax requests.
     * Allow if ...
     * - user is logged in frontend user
     * - is XmlHttpRequest
     * - csrf token is valid.
     *
     * @throws \Exception
     */
    public function __construct(ContaoFramework $framework, Connection $connection, CsrfTokenManagerInterface $tokenManager, RequestStack $requestStack, Security $security, TranslatorInterface $translator, string $projectDir, string $tokenName)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->tokenManager = $tokenManager;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->translator = $translator;
        $this->projectDir = $projectDir;
        $this->tokenName = $tokenName;
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventArticle/setPublishState", name="sac_event_tool_ajax_member_dashboard_write_event_article_set_publish_state", defaults={"_scope" = "frontend"})
     * @return JsonResponse
     * @throws \Doctrine\DBAL\Exception
     */
    public function setPublishStateAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Notification $notificationAdapter */
        $notificationAdapter = $this->framework->getAdapter(Notification::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (!$request->request->get('eventId')) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_calendar_events_story WHERE sacMemberId = ? AND eventId = ? AND publishState < ?',
            [
                $objUser->sacMemberId,
                $request->request->get('eventId'),
                3,
            ],
        );

        if (!$id) {
            return new JsonResponse(['status' => 'error']);
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($id);

        if (null === $objStory) {
            return new JsonResponse(['status' => 'error']);
        }

        // Check for a valid photographer name an existing image legends
        if (!empty($objStory->multiSRC) && !empty($stringUtilAdapter->deserialize($objStory->multiSRC, true))) {
            $arrUuids = $stringUtilAdapter->deserialize($objStory->multiSRC, true);
            $objFiles = $filesModelAdapter->findMultipleByUuids($arrUuids);
            $blnMissingLegend = false;
            $blnMissingPhotographerName = false;

            while ($objFiles->next()) {
                $arrMeta = $stringUtilAdapter->deserialize($objFiles->meta, true);

                if (!isset($arrMeta['de']['caption']) || '' === $arrMeta['de']['caption']) {
                    $blnMissingLegend = true;
                }

                if (!isset($arrMeta['de']['photographer']) || '' === $arrMeta['de']['photographer']) {
                    $blnMissingPhotographerName = true;
                }
            }

            if ($blnMissingLegend || $blnMissingPhotographerName) {
                return new JsonResponse(['status' => 'error']);
            }
        }

        // Notify back office via terminal42/notification_center if there is a new story.
        if ('2' === $request->request->get('publishState') && $objStory->publishState < 2 && $request->request->get('moduleId')) {
            $objModule = $moduleModelAdapter->findByPk($request->request->get('moduleId'));

            if (null !== $objModule) {
                $objNotification = $notificationAdapter->findByPk($objModule->notifyOnEventStoryPublishedNotificationId);
            }

            if (isset($objNotification) && $objNotification && $request->request->get('eventId') > 0) {
                $objEvent = $calendarEventsModelAdapter->findByPk($request->request->get('eventId'));
                $objInstructor = $userModelAdapter->findByPk($objEvent->mainInstructor);
                $instructorName = '';
                $instructorEmail = '';

                if (null !== $objInstructor) {
                    $instructorName = $objInstructor->name;
                    $instructorEmail = $objInstructor->email;
                }

                // Generate frontend preview link
                $previewLink = '';

                if ($objModule->eventStoryJumpTo > 0) {
                    $objTarget = $pageModelAdapter->findByPk($objModule->eventStoryJumpTo);

                    if (null !== $objTarget) {
                        $previewLink = $stringUtilAdapter->ampersand($objTarget->getFrontendUrl(Config::get('useAutoItem') ? '/%s' : '/items/%s'));
                        $previewLink = sprintf($previewLink, $objStory->id);
                        $previewLink = $environmentAdapter->get('url').'/'.$urlAdapter->addQueryString('securityToken='.$objStory->securityToken, $previewLink);
                    }
                }

                // Notify webmaster
                $arrNotifyEmail = [];
                $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

                foreach ($arrOrganizers as $orgId) {
                    $objEventOrganizer = $eventOrganizerModelAdapter->findByPk($orgId);

                    if (null !== $objEventOrganizer) {
                        $arrUsers = $stringUtilAdapter->deserialize($objEventOrganizer->notifyWebmasterOnNewEventStory, true);

                        foreach ($arrUsers as $userId) {
                            $objWebmaster = $userModelAdapter->findByPk($userId);

                            if (null !== $objWebmaster) {
                                if ('' !== $objWebmaster->email) {
                                    if ($validatorAdapter->isEmail($objWebmaster->email)) {
                                        $arrNotifyEmail[] = $objWebmaster->email;
                                    }
                                }
                            }
                        }
                    }
                }

                $webmasterEmail = implode(',', $arrNotifyEmail);

                $arrTokens = [];

                if (null !== $objEvent) {
                    $arrTokens = array_merge($arrTokens, [
                        'event_title' => $objEvent->title,
                        'event_id' => $objEvent->id,
                        'instructor_name' => '' !== $instructorName ? $instructorName : $this->translator->trans('MSC.notSpecified', [], 'contao_default'),
                        'instructor_email' => '' !== $instructorEmail ? $instructorEmail : $this->translator->trans('MSC.notSpecified', [], 'contao_default'),
                        'webmaster_email' => '' !== $webmasterEmail ? $webmasterEmail : '',
                        'author_name' => $objUser->firstname.' '.$objUser->lastname,
                        'author_email' => $objUser->email,
                        'author_sac_member_id' => $objUser->sacMemberId,
                        'hostname' => $environmentAdapter->get('host'),
                        'story_link_backend' => $environmentAdapter->get('url').'/contao?do=sac_calendar_events_stories_tool&act=edit&id='.$objStory->id,
                        'story_link_frontend' => $previewLink,
                        'story_title' => $objStory->title,
                        'story_text' => $objStory->text,
                    ]);
                }

                // Send notification
                $objNotification->send($arrTokens, 'de');
            }
        }

        // Save publish state
        $objStory->publishState = $request->request->get('publishState');
        $objStory->save();

        $json = [
            'status' => 'success',
            'publishState' => $objStory->publishState,
        ];

        return new JsonResponse($json);
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventArticle/sortGallery", name="sac_event_tool_ajax_member_dashboard_write_event_article_sort_gallery", defaults={"_scope" = "frontend"})
     *
     * @throws \Exception
     */
    public function sortGalleryAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (!$request->request->get('uuids') || !$request->request->get('eventId') || !FE_USER_LOGGED_IN) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_calendar_events_story WHERE sacMemberId = ? AND eventId = ?',
            [
                $objUser->sacMemberId,
                $request->request->get('eventId'),
            ],
        );

        if (!$id) {
            return new JsonResponse(['status' => 'error']);
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($id);

        if (null === $objStory) {
            return new JsonResponse(['status' => 'error']);
        }

        $arrSorting = json_decode($request->request->get('uuids'));
        $arrSorting = array_map(
            static fn ($uuid) => $stringUtilAdapter->uuidToBin($uuid),
            $arrSorting
        );

        $objStory->orderSRC = serialize($arrSorting);
        $objStory->save();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @throws \Exception
     * @Route("/ajaxMemberDashboardWriteEventArticle/removeImage", name="sac_event_tool_ajax_member_dashboard_write_event_article_remove_image", defaults={"_scope" = "frontend"})
     */
    public function removeImageAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (!$request->request->get('eventId') || !$request->request->get('uuid')) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        $id = $this->connection->fetchOne(
            'SELECT * FROM tl_calendar_events_story WHERE sacMemberId = ? && eventId = ? && publishState < ?',
            [
                $objUser->sacMemberId,
                $request->request->get('eventId'),
                3,
            ]
        );

        if (!$id) {
            return new JsonResponse(['status' => 'error']);
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($id);

        if (null === $objStory) {
            return new JsonResponse(['status' => 'error']);
        }

        $multiSrc = $stringUtilAdapter->deserialize($objStory->multiSRC, true);
        $orderSrc = $stringUtilAdapter->deserialize($objStory->orderSRC, true);

        $uuid = $stringUtilAdapter->uuidToBin($request->request->get('uuid'));

        if (!$validatorAdapter->isUuid($uuid)) {
            return new JsonResponse(['status' => 'error']);
        }

        $key = array_search($uuid, $multiSrc, true);

        if (false !== $key) {
            unset($multiSrc[$key]);
            $multiSrc = array_values($multiSrc);
            $objStory->multiSRC = serialize($multiSrc);
        }

        $key = array_search($uuid, $orderSrc, true);

        if (false !== $key) {
            unset($orderSrc[$key]);
            $orderSrc = array_values($multiSrc);
            $objStory->orderSRC = serialize($orderSrc);
        }

        // Save model
        $objStory->save();

        // Delete image from filesystem and db
        $filesModel = $filesModelAdapter->findByUuid($uuid);

        if (null !== $filesModel) {
            $fs = new Filesystem();
            $fs->remove($this->projectDir . '/' . $filesModel->path);

            $filesModel->delete();
        }

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @throws \Exception
     * @Route("/ajaxMemberDashboardWriteEventArticle/rotateImage", name="sac_event_tool_ajax_member_dashboard_write_event_article_rotate_image", defaults={"_scope" = "frontend"})
     */
    public function rotateImageAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $fileId = $request->request->get('fileId');

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        // Get the image rotate service
        $objFiles = $filesModelAdapter->findOneById($fileId);
        $objRotateImage = System::getContainer()->get('Markocupic\SacEventToolBundle\Image\RotateImage');

        if ($objRotateImage->rotate($objFiles)) {
            $json = ['status' => 'success'];
        } else {
            $json = ['status' => 'error'];
        }

        return new JsonResponse($json);
    }

    /**
     * @throws \Exception
     * @Route("/ajaxMemberDashboardWriteEventArticle/getCaption", name="sac_event_tool_ajax_member_dashboard_write_event_article_get_caption", defaults={"_scope" = "frontend"})
     */
    public function getCaptionAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $objUser = $this->security->getUser();

        if ('' !== $request->request->get('fileUuid')) {
            $objFile = $filesModelAdapter->findByUuid($request->request->get('fileUuid'));

            if (null !== $objFile) {
                $arrMeta = $stringUtilAdapter->deserialize($objFile->meta, true);

                if (!isset($arrMeta['de']['caption'])) {
                    $caption = '';
                } else {
                    $caption = $arrMeta['de']['caption'];
                }

                if (!isset($arrMeta['de']['photographer'])) {
                    $photographer = $objUser->firstname.' '.$objUser->lastname;
                } else {
                    $photographer = $arrMeta['de']['photographer'];

                    if ('' === $photographer) {
                        $photographer = $objUser->firstname.' '.$objUser->lastname;
                    }
                }

                return new JsonResponse([
                    'status' => 'success',
                    'caption' => html_entity_decode((string) $caption),
                    'photographer' => $photographer,
                ]);
            }
        }

        return new JsonResponse(['status' => 'error']);
    }

    /**
     * @throws \Exception
     * @Route("/ajaxMemberDashboardWriteEventArticle/setCaption", name="sac_event_tool_ajax_member_dashboard_write_event_article_set_caption", defaults={"_scope" = "frontend"})
     */
    public function setCaptionAction(): JsonResponse
    {
        $this->framework->initialize();
        $this->checkHasLoggedInFrontendUser();
        $this->checkIsTokenValid();
        $this->checkIsXmlHttpRequest();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        if ('' !== $request->request->get('fileUuid')) {
            $objUser = $this->security->getUser();

            if (!$objUser instanceof FrontendUser) {
                return new JsonResponse(['status' => 'error']);
            }

            $objFile = $filesModelAdapter->findByUuid($request->request->get('fileUuid'));

            if (null !== $objFile) {
                $arrMeta = $stringUtilAdapter->deserialize($objFile->meta, true);

                if (!isset($arrMeta['de'])) {
                    $arrMeta['de'] = [
                        'title' => '',
                        'alt' => '',
                        'link' => '',
                        'caption' => '',
                        'photographer' => '',
                    ];
                }
                $arrMeta['de']['caption'] = $request->request->get('caption');
                $arrMeta['de']['photographer'] = $request->request->get('photographer') ?: $objUser->firstname.' '.$objUser->lastname;

                $objFile->meta = serialize($arrMeta);
                $objFile->save();

                return new JsonResponse(['status' => 'success']);
            }
        }

        return new JsonResponse(['status' => 'error']);
    }

    /**
     * @throws \Exception
     */
    private function checkHasLoggedInFrontendUser(): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            throw new \Exception('Access denied! You have to be logged in as a Contao frontend user');
        }
    }

    private function checkIsTokenValid(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $request->get('REQUEST_TOKEN')))) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }

    private function checkIsXmlHttpRequest(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException('The route "/ajaxMemberDashboardWriteEventArticle" is allowed to XMLHttpRequest requests only.');
        }
    }
}
