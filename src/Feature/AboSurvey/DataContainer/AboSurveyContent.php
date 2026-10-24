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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * tl_content fields of the content element "abo_survey".
 */
class AboSurveyContent
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * HTML explanation at the top of the edit view (the field has no database column).
     * The text is in contao/languages/<lang>/tl_content.php (aboSurveyExplanation).
     */
    #[AsCallback(table: 'tl_content', target: 'fields.aboSurveyExplanation.input_field')]
    public function renderExplanation(): string
    {
        return \sprintf(
            '<div class="widget clr long abo-survey-explanation"><div class="tl_info" style="height:auto;line-height:1.6;padding-top:10px;padding-bottom:10px">%s</div></div>',
            $this->translator->trans('tl_content.aboSurveyExplanation', [], 'contao_tl_content'),
        );
    }

    #[AsCallback(table: 'tl_content', target: 'fields.aboSurveyAllowedUserRoles.options')]
    public function getUserRoles(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id, title FROM tl_user_role ORDER BY sorting');
    }

    #[AsCallback(table: 'tl_content', target: 'fields.aboSurveyAllowedUsers.options')]
    public function getUsers(): array
    {
        $options = [];

        foreach ($this->connection->fetchAllAssociative('SELECT id, lastname, firstname, sacMemberId FROM tl_user ORDER BY lastname, firstname') as $user) {
            $options[$user['id']] = trim(\sprintf('%s %s', $user['lastname'], $user['firstname'])).($user['sacMemberId'] ? \sprintf(' (%s)', $user['sacMemberId']) : '');
        }

        return $options;
    }
}
