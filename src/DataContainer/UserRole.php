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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserRole
{
    public function __construct(
        private RequestStack $requestStack,
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsCallback(table: 'tl_user_role', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc = null): void
    {
        if (null === $dc || !$dc->id) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $act = $request->query->get('act');

        switch ($act) {
            case 'cut': // Do not allow the paste into mode
                if ('1' !== $request->query->get('mode')) {
                    throw new AccessDeniedException('The paste into operation is not allowed on this record!');
                }
                break;
        }
    }

    /**
     * Add the "role currently vacant" label to each record,
     * if the user role could not be found in tl_user.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user_role', target: 'list.label.label', priority: 100)]
    public function checkForUsage(array $row, string $label, DataContainer $dc, string $args): string
    {
        $arrRoles = [];

        $arrUserRoles = $this->connection->fetchFirstColumn('SELECT userRole FROM tl_user');

        if (!empty($arrUserRoles)) {
            foreach ($arrUserRoles as $roles) {
                $arrRecord = StringUtil::deserialize($roles, true);
                $arrRoles = array_merge($arrRecord, $arrRoles);
            }
        }

        $arrRoles = array_values(array_unique($arrRoles));

        $blnUsed = \in_array($row['id'], $arrRoles, false);

        $msg = $this->translator->trans('MSC.roleCurrentlyVacant', [], 'contao_default');

        $style = !$blnUsed ? sprintf(' title="%s" style="color:red"', htmlspecialchars($msg)) : '';

        return sprintf('<span%s>%s</span> <span style="color:grey">%s</span>', $style, $row['title'], $row['email']);
    }

    /**
     * Do not show the paste into button.
     */
    #[AsCallback(table: 'tl_user_role', target: 'list.sorting.paste_button', priority: 100)]
    public function pasteButtonCallback(DataContainer $dc, array $row, string $strTable, bool $blnCircularRef, array $arrClipboard, array|null $children, string|null $previousLabel, string|null $nextLabel): string
    {
        if (isset($arrClipboard['id']) && (int) $arrClipboard['id'] === (int) $row['id']) {
            return Image::getHtml('pasteafter--disabled.svg').' ';
        }

        $imagePasteAfter = Image::getHtml('pasteafter.svg', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'));

        return '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default')).'" data-action="contao--scroll-offset#store">'.$imagePasteAfter.'</a> ';
    }
}
