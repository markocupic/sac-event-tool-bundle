<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
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
use Contao\Database;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Haste\Util\Url;
use NotificationCenter\Model\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class MemberDashboardWriteEventReportController.
 */
class MemberDashboardWriteEventReportController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var CsrfTokenManagerInterface
     */
    private $tokenManager;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var string
     */
    private $tokenName;

    /**
     * MemberDashboardWriteEventReportController constructor.
     * Handles ajax requests.
     * Allow if ...
     * - user is logged in frontend user
     * - is XmlHttpRequest
     * - csrf token is valid.
     *
     * @throws \Exception
     */
    public function __construct(ContaoFramework $framework, CsrfTokenManagerInterface $tokenManager, RequestStack $requestStack, Security $security, string $tokenName)
    {
        $this->framework = $framework;
        $this->tokenManager = $tokenManager;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->tokenName = $tokenName;

        $this->framework->initialize();

        /** @var FrontendUser $user */
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            throw new \Exception('You have to be logged in as a Contao Frontend User');
        }

        /** @var FrontendUser $user */
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            throw new \Exception('You have to be logged in as a Contao Frontend User');
        }

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Validate request token
        if (!$this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $request->get('REQUEST_TOKEN')))) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException('The route "/ajaxMemberDashboardWriteEventReport" is allowed to XMLHttpRequest requests only.');
        }
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventReport/setPublishState", name="sac_event_tool_ajax_member_dashboard_write_event_report_set_publish_state", defaults={"_scope" = "frontend"})
     */
    public function setPublishStateAction(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

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

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

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

        if (!$request->request->get('eventId') || !FE_USER_LOGGED_IN) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            return new JsonResponse(['status' => 'error']);
        }

        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND eventId=? AND publishState<?')->limit(1)->execute($objUser->sacMemberId, $request->request->get('eventId'), 3);

        if (!$objDb->numRows) {
            return new JsonResponse(['status' => 'error']);
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);

        if (null === $objStory) {
            return new JsonResponse(['status' => 'error']);
        }

        // Notify office if there is a new story
        if ('2' === $request->request->get('publishState') && $objStory->publishState < 2 && $request->request->get('moduleId')) {
            $objModule = $moduleModelAdapter->findByPk($request->request->get('moduleId'));

            if (null !== $objModule) {
                // Use terminal42/notification_center
                $objNotification = $notificationAdapter->findByPk($objModule->notifyOnEventStoryPublishedNotificationId);
            }

            if (null !== $objNotification && null !== $objUser && $request->request->get('eventId') > 0) {
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
                        $previewLink = ampersand($objTarget->getFrontendUrl(Config::get('useAutoItem') ? '/%s' : '/items/%s'));
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

                if (null !== $objEvent) {
                    $arrTokens = [
                        'event_title' => $objEvent->title,
                        'event_id' => $objEvent->id,
                        'instructor_name' => '' !== $instructorName ? $instructorName : 'keine Angabe',
                        'instructor_email' => '' !== $instructorEmail ? $instructorEmail : 'keine Angabe',
                        'webmaster_email' => '' !== $webmasterEmail ? $webmasterEmail : '',
                        'author_name' => $objUser->firstname.' '.$objUser->lastname,
                        'author_email' => $objUser->email,
                        'author_sac_member_id' => $objUser->sacMemberId,
                        'hostname' => $environmentAdapter->get('host'),
                        'story_link_backend' => $environmentAdapter->get('url').'/contao?do=sac_calendar_events_stories_tool&act=edit&id='.$objStory->id,
                        'story_link_frontend' => $previewLink,
                        'story_title' => $objStory->title,
                        'story_text' => $objStory->text,
                    ];
                }

                // Send notification
                $objNotification->send($arrTokens, 'de');
            }
        }

        // Save publish state
        $objStory->publishState = $request->request->get('publishState');
        $objStory->save();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventReport/sortGallery", name="sac_event_tool_ajax_member_dashboard_write_event_report_sort_gallery", defaults={"_scope" = "frontend"})
     */
    public function sortGalleryAction(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        if (!$request->request->get('uuids') || !$request->request->get('eventId') || !FE_USER_LOGGED_IN) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            return new JsonResponse(['status' => 'error']);
        }

        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objUser->sacMemberId, $request->request->get('eventId'));

        if (!$objDb->numRows) {
            return new JsonResponse(['status' => 'error']);
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);

        if (null === $objStory) {
            return new JsonResponse(['status' => 'error']);
        }

        $arrSorting = json_decode($request->request->get('uuids'));
        $arrSorting = array_map(
            function ($uuid) {
                /** @var StringUtil $stringUtilAdapter */
                $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

                return $stringUtilAdapter->uuidToBin($uuid);
            },
            $arrSorting
        );

        $objStory->orderSRC = serialize($arrSorting);
        $objStory->save();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @throws \Exception
     * @Route("/ajaxMemberDashboardWriteEventReport/removeImage", name="sac_event_tool_ajax_member_dashboard_write_event_report_remove_image", defaults={"_scope" = "frontend"})
     */
    public function removeImageAction(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (!$request->request->get('eventId') || !$request->request->get('uuid') || !FE_USER_LOGGED_IN) {
            return new JsonResponse(['status' => 'error']);
        }

        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            return new JsonResponse(['status' => 'error']);
        }
        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, $request->request->get('eventId'), 3);

        if (!$objDb->numRows) {
            return new JsonResponse(['status' => 'error']);
        }
        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);

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
        $objFile = $filesModelAdapter->findByUuid($uuid);

        if (null !== $objFile) {
            $oFile = new File($objFile->path);
            $oFile->delete();
            $objFile->delete();
        }

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventReport/rotateImage", name="sac_event_tool_ajax_member_dashboard_write_event_report_rotate_image", defaults={"_scope" = "frontend"})
     */
    public function rotateImageAction(): JsonResponse
    {
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
     * @Route("/ajaxMemberDashboardWriteEventReport/getCaption", name="sac_event_tool_ajax_member_dashboard_write_event_report_get_caption", defaults={"_scope" = "frontend"})
     */
    public function getCaptionAction(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $objUser = $this->security->getUser();

        if (!$objUser instanceof FrontendUser) {
            return new JsonResponse(['status' => 'error']);
        }

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
                    'caption' => html_entity_decode($caption),
                    'photographer' => $photographer,
                ]);
            }
        }

        return new JsonResponse(['status' => 'error']);
    }

    /**
     * @Route("/ajaxMemberDashboardWriteEventReport/setCaption", name="sac_event_tool_ajax_member_dashboard_write_event_report_set_caption", defaults={"_scope" = "frontend"})
     */
    public function setCaptionAction(): JsonResponse
    {
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
}
