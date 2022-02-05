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

use Contao\Controller;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Message;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;
use Symfony\Contracts\Translation\TranslatorInterface;

class Member
{
    private TranslatorInterface $translator;
    private ClearFrontendUserData $clearFrontendUserData;

    public function __construct(TranslatorInterface $translator, ClearFrontendUserData $clearFrontendUserData)
    {
        $this->translator = $translator;
        $this->clearFrontendUserData = $clearFrontendUserData;
    }

    /**
     * @Callback(table="tl_member", target="config.delete")
     *
     * @throws \Exception
     */
    public function clearMemberProfile(DataContainer $dc): void
    {
        // Clear personal data f.ex.
        // - Anonymize entries in tl_calendar_events_member
        // - Delete avatar directory

        if (!$dc->id) {
            return;
        }

        if (false === $this->clearFrontendUserData->clearMemberProfile((int) $dc->id)) {
            $arrErrorMsg = $this->translator->trans('ERR.clearMemberProfile', [$dc->id], 'contao_default');
            Message::addError($arrErrorMsg);

            Controller::redirect('contao?do=member');
        }
    }
}
