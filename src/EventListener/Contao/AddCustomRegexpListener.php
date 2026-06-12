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
use Contao\MemberModel;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventDurationInfo;
use Markocupic\SacEventToolBundle\String\Validator\AhvValidator;
use Markocupic\SacEventToolBundle\String\Validator\CashAmountValidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class AddCustomRegexpListener
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private EventDurationInfo $eventDurationInfo,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isValidAhvNumber(string $regexp, $input, Widget $widget): bool
    {
        if ('ahv' !== $regexp) {
            return false;
        }

        if (!AhvValidator::validate($input)) {
            $widget->addError($this->translator->trans('ERR.invalidAhvNumber', [], 'contao_default'));
        }

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isValidDurationInfo(string $regexp, $input, Widget $widget): bool
    {
        if ('durationInfo' !== $regexp) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();

        // $request->request->get('eventDates') will throw an exception because
        // $_POST['eventDates'] is a non-scalar value.
        $post = $request->request->all();

        if (empty($input) || empty($post['eventDates'][0])) {
            return true;
        }

        if (!$this->eventDurationInfo->has($input)) {
            return true;
        }

        $arrDurationInfo = $this->eventDurationInfo->get($input);
        $countDates = \count($post['eventDates']);

        if ($arrDurationInfo['dateRows'] !== $countDates) {
            $widget->addError($this->translator->trans('ERR.invalidEventDurationInfo', [], 'contao_default'));
        }

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isSacMemberIdEmptyOrString(string $regexp, $input, Widget $widget): bool
    {
        if ('sacMemberIdOrEmptyString' !== $regexp) {
            return false;
        }

        if ('' === $input) {
            return true;
        }

        if (preg_match('/^[1-9]\d{5,}$/', $input)) {
            $memberModel = $this->framework->getAdapter(MemberModel::class)->findOneBySacMemberId($input);

            if (null === $memberModel) {
                $widget->addError($this->translator->trans('ERR.memberWithSACMemberIdNotFound', [$input], 'contao_default'));

                return true;
            }

            // All ok!
            return true;
        }

        $widget->addError($this->translator->trans('ERR.SACMemberIdShouldBeNumberOrEmptyString', [], 'contao_default'));

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isSacMemberIdUniqueOrZero(string $regexp, $input, Widget $widget): bool
    {
        if ('sacMemberIdIsUniqueOrZero' !== $regexp) {
            return false;
        }

        if (0 === $input || '0' === $input) {
            return true;
        }

        if (!preg_match('/^[1-9]\d{5,}$/', $input)) {
            $widget->addError($this->translator->trans('ERR.SACMemberIdShouldBeNumberOrZero', [], 'contao_default'));

            return true;
        }

        $memberModel = $this->framework->getAdapter(MemberModel::class)->findOneBySacMemberId($input);

        if (null === $memberModel) {
            $widget->addError($this->translator->trans('ERR.memberWithSACMemberIdNotFound', [$input], 'contao_default'));

            return true;
        }

        $count = $this->connection->fetchOne('SELECT COUNT(id) FROM tl_user WHERE sacMemberId = ?', [$input], [Types::INTEGER]);

        if ($count > 1) {
            $widget->addError($this->translator->trans('ERR.userWithThisSACMemberIdAlreadyExists', [$input], 'contao_default'));
        }

        return true;
    }

    #[AsHook('addCustomRegexp', priority: 100)]
    public function isPositiveMoney(string $regexp, $input, Widget $objWidget): bool
    {
        if ('positiveCashAmount' !== $regexp) {
            return false;
        }

        // Valid values are 0 or 123.45 or 123.4. Invalid values are 123.456789 or -123.45.
        if (CashAmountValidator::isPositiveCashAmount($input)) {
            return true;
        }

        $objWidget->addError($this->translator->trans('ERR.mustBePositiveCashAmount', [], 'contao_default'));

        return true;
    }
}
