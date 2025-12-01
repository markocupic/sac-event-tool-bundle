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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\MemberModel;
use Contao\Widget;
use Markocupic\SacEventToolBundle\Config\EventDurationInfo;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class AddCustomRegexpListener
{
    public function __construct(
        private TranslatorInterface $translator,
        private ContaoFramework $framework,
        private EventDurationInfo $eventDurationInfo,
        private RequestStack $requestStack,
    ) {
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isValidDurationInfo(string $strRegexp, $varValue, Widget $objWidget): bool
    {
        if ('durationInfo' !== $strRegexp) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();

        // $request->request->get('eventDates') will throw an exception because
        // $_POST['eventDates'] is a non-scalar value.
        $post = $request->request->all();

        if (empty($varValue) || empty($post['eventDates'][0])) {
            return true;
        }

        if (!$this->eventDurationInfo->has($varValue)) {
            return true;
        }

        $arrDurationInfo = $this->eventDurationInfo->get($varValue);
        $countDates = \count($post['eventDates']);

        if ($arrDurationInfo['dateRows'] !== $countDates) {
            $objWidget->addError($this->translator->trans('ERR.invalidEventDurationInfo', [], 'contao_default'));
        }

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isSacMemberIdEmptyOrString(string $strRegexp, $varValue, Widget $objWidget): bool
    {
        if ('sacMemberIdOrEmptyString' !== $strRegexp) {
            return false;
        }

        if ('' === $varValue) {
            return true;
        }

        if (preg_match('/^[1-9]\d{5,}$/', $varValue)) {
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

            $objMemberModel = $memberModelAdapter->findOneBySacMemberId(trim($varValue));

            if (null === $objMemberModel) {
                $objWidget->addError($this->translator->trans('ERR.memberWithSACMemberIdNotFound', [$varValue], 'contao_default'));

                return true;
            }

            return true;
        }

        $objWidget->addError($this->translator->trans('ERR.SACMemberIdShouldBeNumberOrEmptyString', [], 'contao_default'));

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isSacMemberIdUniqueOrZero(string $strRegexp, $varValue, Widget $objWidget): bool
    {
        if ('sacMemberIdIsUniqueOrZero' !== $strRegexp) {
            return false;
        }

        if (0 === $varValue || '0' === $varValue) {
            return true;
        }

        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        if (preg_match('/^[1-9]\d{5,}$/', $varValue)) {
            $objMemberModel = $memberModelAdapter->findOneBySacMemberId($varValue);

            if (null === $objMemberModel) {
                $objWidget->addError($this->translator->trans('ERR.memberWithSACMemberIdNotFound', [$varValue], 'contao_default'));

                return true;
            }

            $objUser = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId = ?')->execute($varValue);

            if ($objUser->numRows > 1) {
                $objWidget->addError($this->translator->trans('ERR.userWithThisSACMemberIdAlreadyExists', [$varValue], 'contao_default'));

                return true;
            }

            return true;
        }

        $objWidget->addError($this->translator->trans('ERR.SACMemberIdShouldBeNumberOrZero', [], 'contao_default'));

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isPositiveMoney(string $strRegexp, $varValue, Widget $objWidget): bool
    {
        if ('positiveMoneyValue' !== $strRegexp) {
            return false;
        }

        // Valid values are 0 or 123.45 or 12345. Invalid values are 123.4 123.456789 or -123.45.
        if (1 === preg_match('/^(?:0|[1-9]\d*)(?:\.\d{2})?$/', $varValue)) {
            return true;
        }

        $objWidget->addError($this->translator->trans('ERR.valueMustBePosMoney', [], 'contao_default'));

        return true;
    }
}
