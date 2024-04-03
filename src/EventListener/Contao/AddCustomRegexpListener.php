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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\MemberModel;
use Contao\Widget;
use Markocupic\SacEventToolBundle\Config\EventDurationInfo;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook('addCustomRegexp', priority: 100)]
readonly class AddCustomRegexpListener
{
    public function __construct(
        private ContaoFramework $framework,
        private RequestStack $requestStack,
	    private EventDurationInfo $eventDurationInfo,
    ) {
    }

    public function __invoke(string $strRegexp, $varValue, Widget $objWidget): bool
    {
        // Check for a valid duration info: tl_calendar_events.durationInfo
        if ('durationInfo' === $strRegexp) {
            $request = $this->requestStack->getCurrentRequest();

            // $request->request->get('eventDates') will throw an exception,
            // because $_POST['eventDates'] is a non-scalar value.
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
                $objWidget->addError($GLOBALS['TL_LANG']['ERR']['invalidEventDurationInfo']);

                return false;
            }

            return true;
        }

        // Set adapters
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        // Check for a valid/existent sacMemberId
        if ('sacMemberId' === $strRegexp) {
            if ('' !== trim($varValue)) {
                $objMemberModel = $memberModelAdapter->findOneBySacMemberId(trim($varValue));

                if (null === $objMemberModel) {
                    $objWidget->addError('Field '.$objWidget->label.' should be a valid sac member id.');
                }
            }

            return true;
        }

        // Check for a valid/existent sacMemberId
        if ('sacMemberIdIsUniqueAndValid' === $strRegexp) {
            if (!is_numeric($varValue)) {
                $objWidget->addError('Sac member id must be a number >= 0');
            } elseif ('' !== trim($varValue) && $varValue > 0) {
                $objMemberModel = $memberModelAdapter->findOneBySacMemberId(trim($varValue));

                if (null === $objMemberModel) {
                    $objWidget->addError('Field '.$objWidget->label.' should be a valid sac member id.');
                }

                $objUser = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId = ?')->execute($varValue);

                if ($objUser->numRows > 1) {
                    $objWidget->addError('SAC member id '.$varValue.' is already in use.');
                }
            }

            return true;
        }

        return false;
    }
}
