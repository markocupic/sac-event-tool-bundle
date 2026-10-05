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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserRole
{
    /**
     * The ids of the user roles assigned to at least one user.
     */
    private array|null $assignedRoleIds = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * User roles can only be sorted, the "paste into" mode is not allowed.
     */
    #[AsCallback(table: 'tl_user_role', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ('cut' === $request->query->get('act') && '1' !== $request->query->get('mode')) {
            throw new AccessDeniedException('The paste into operation is not allowed on this record!');
        }
    }

    /**
     * Mark user roles that are not assigned to any user as "currently vacant".
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user_role', target: 'list.label.label', priority: 100)]
    public function checkForUsage(array $row, string $label, DataContainer $dc, string $args): string
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $style = '';

        if (!\in_array($row['id'], $this->getAssignedRoleIds(), false)) {
            $msg = $this->translator->trans('MSC.roleCurrentlyVacant', [], 'contao_default');
            $style = \sprintf(' title="%s" style="color:red"', $stringUtil->specialchars($msg));
        }

        return \sprintf('<span%s>%s</span> <span style="color:grey">%s</span>', $style, $stringUtil->specialchars($row['title']), $stringUtil->specialchars($row['email']));
    }

    /**
     * Only show the "paste after" button. A record cannot be pasted after itself.
     */
    #[AsCallback(table: 'tl_user_role', target: 'list.sorting.paste_button', priority: 100)]
    public function pasteButtonCallback(DataContainer $dc, array $row, string $table, bool $circularReference, array $clipboard, array|null $children, string|null $previousLabel, string|null $nextLabel): string
    {
        $image = $this->framework->getAdapter(Image::class);

        if (isset($clipboard['id']) && (int) $clipboard['id'] === (int) $row['id']) {
            return $image->getHtml('pasteafter--disabled.svg').' ';
        }

        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $title = $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default');
        $href = $this->framework->getAdapter(Backend::class)->addToUrl('act='.$clipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].(!\is_array($clipboard['id']) ? '&amp;id='.$clipboard['id'] : ''));

        return '<a href="'.$stringUtil->specialcharsUrl($href).'" title="'.$stringUtil->specialchars($title).'" data-action="contao--scroll-offset#store">'.$image->getHtml('pasteafter.svg', $title).'</a> ';
    }

    /**
     * @throws Exception
     */
    private function getAssignedRoleIds(): array
    {
        if (null === $this->assignedRoleIds) {
            $stringUtil = $this->framework->getAdapter(StringUtil::class);
            $roleIds = [];

            foreach ($this->connection->fetchFirstColumn('SELECT userRole FROM tl_user') as $userRoles) {
                $roleIds = array_merge($roleIds, $stringUtil->deserialize($userRoles, true));
            }

            $this->assignedRoleIds = array_values(array_unique($roleIds));
        }

        return $this->assignedRoleIds;
    }
}
