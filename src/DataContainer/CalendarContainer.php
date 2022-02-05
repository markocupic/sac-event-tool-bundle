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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

class CalendarContainer
{
    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private Security $security;

    /**
     * Import the back end user object.
     */
    public function __construct(RequestStack $requestStack, Connection $connection, Util $util, Security $security)
    {
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->security = $security;
    }

    /**
     * Check permissions to edit table tl_calendar.
     *
     * @Callback(table="tl_calendar", target="config.onload")
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Check permissions to edit table tl_calendar.
     *
     * @Callback(table="tl_calendar_container", target="config.onload")
     */
    public function checkPermission(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($user->isAdmin) {
            return;
        }

        // Set root IDs
        if (!\is_array($user->calendar_containers) || empty($user->calendar_containers)) {
            $root = [0];
        } else {
            $root = $user->calendar_containers;
        }

        $GLOBALS['TL_DCA']['tl_calendar_container']['list']['sorting']['root'] = $root;

        // Check permissions to add calendar_containers
        if (!$user->hasAccess('create', 'calendar_containerp')) {
            $GLOBALS['TL_DCA']['tl_calendar_container']['config']['closed'] = true;
        }

        /** @var SessionInterface $objSession */
        $session = $request->getSession();

        // Check current action
        switch ($request->query->get('act')) {
            case 'create':
            case 'select':
                // Allow
                break;

            case 'edit':
                // Dynamically add the record to the user profile
                if (!\in_array($request->query->get('id'), $root, false)) {
                    /** @var AttributeBagInterface $bag */
                    $bag = $session->getBag('contao_backend');

                    $arrNew = $bag->get('new_records');

                    if (\is_array($arrNew['tl_calendar_container']) && \in_array($request->query->get('id'), $arrNew['tl_calendar_container'], false)) {
                        // Add the permissions on group level
                        if ('custom' !== $user->inherit) {
                            $stmt = $this->connection
                                ->executeQuery(
                                    'SELECT * FROM tl_user_group WHERE id IN('.implode(',', array_map('intval', $user->groups)).')'
                                )
                            ;

                            while (false !== ($arrGroup = $stmt->fetchAssociative())) {
                                $arrCalendarContainerp = StringUtil::deserialize($arrGroup['calendar_containerp']);

                                if (\is_array($arrCalendarContainerp) && \in_array('create', $arrCalendarContainerp, true)) {
                                    $arrCalendarContainers = StringUtil::deserialize($arrGroup['calendar_containers'], true);
                                    $arrCalendarContainers[] = $request->query->get('id');

                                    $this->connection->executeStatement(
                                        'UPDATE tl_user_group SET calendar_containers = ? WHERE id = ?',
                                        [
                                            serialize($arrCalendarContainers),
                                            $arrGroup['id'],
                                        ]
                                    );
                                }
                            }
                        }

                        // Add the permissions on user level
                        if ('group' !== $user->inherit) {
                            $arrCalendarContainerp = StringUtil::deserialize($arrGroup['calendar_containerp']);

                            if (\is_array($arrCalendarContainerp) && \in_array('create', $arrCalendarContainerp, true)) {
                                $arrCalendarContainers = StringUtil::deserialize($arrGroup['calendar_containers'], true);

                                $arrCalendarContainers[] = $request->query->get('id');

                                $this->connection->executeStatement(
                                    'UPDATE tl_user SET calendar_containers = ? WHERE id = ?',
                                    [
                                        serialize($arrCalendarContainers),
                                        $user->id,
                                    ]
                                );
                            }
                        }

                        // Add the new element to the user object
                        $root[] = $request->query->get('id');
                        $user->calendar_containers = $root;
                    }
                }
            // no break;

            case 'copy':
            case 'delete':
            case 'show':
                if (!\in_array($request->query->get('id'), $root, false) || ('delete' === $request->query->get('act') && !$user->hasAccess('delete', 'calendar_containerp'))) {
                    throw new AccessDeniedException('Not enough permissions to '.$request->query->get('act').' calendar ID '.$request->query->get('id').'.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
                $arrSession = $objSession->all();

                if ('deleteAll' === $request->query->get('act') && !$user->hasAccess('delete', 'calendar_containerp')) {
                    $arrSession['CURRENT']['IDS'] = [];
                } else {
                    $arrSession['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $root);
                }
                $session->replace($arrSession);
                break;

            default:
                if (\strlen((string) $request->query->get('act'))) {
                    throw new AccessDeniedException('Not enough permissions to '.$request->query->get('act').' calendar_containers.');
                }
                break;
        }
    }

    /**
     * Return the edit header button.
     *
     * @Callback(table="tl_calendar_container", target="operations.editheader.button")
     */
    public function editHeaderButton(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        return $user->canEditFieldsOf('tl_calendar_container') ? '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the copy calendar button.
     *
     * @Callback(table="tl_calendar_container", target="operations.copy.button")
     */
    public function copyButton(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        return $user->hasAccess('create', 'calendar_containerp') ? '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the delete calendar button.
     *
     * @Callback(table="tl_calendar_container", target="operations.delete.button")
     */
    public function deleteButton(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        return $user->hasAccess('delete', 'calendar_containerp') ? '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
    }
}
