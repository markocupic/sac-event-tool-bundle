<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Database\Result;
use Contao\Email;
use Doctrine\DBAL\Connection;

#[AsHook('sendNewsletter')]
class SendNewsletterListener
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(Email $email, Result $newsletter, array $recipient, string $text, string $html): void
    {
        $rec = [];
        $fields = ['id', 'pid', 'email', 'firstname', 'lastname', 'city', 'street', 'sacMemberId'];

        foreach ($fields as $field) {
            $rec[$field] = $recipient[$field];
        }

        if (!empty($recipient['pid'])) {
            $arrNewsletter = $newsletter->row();

            if ($arrNewsletter['deleteRecipientOnNewsletterSend']) {
                $this->connection->delete('tl_newsletter_recipients', ['pid' => $rec['pid'], 'email' => $rec['email']]);
            }
        }
    }
}
