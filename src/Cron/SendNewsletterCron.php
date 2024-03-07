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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\Email;
use Contao\File;
use Contao\FilesModel;
use Contao\Idna;
use Contao\MemberModel;
use Contao\NewsletterBundle\Event\SendNewsletterEvent;
use Contao\NewsletterModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * This cron job will send a number of messages specified in the newsletter every minute.
 * Each recipient will then be removed from the list.
 * The process repeats until there are no more active recipients in the list.
 * The prerequisite is that tl_newsletter.enableSendAndDeleteCron has been activated
 * and (tl_newsletter.enableSendAndDeleteCron is empty or tl_newsletter.enableSendAndDeleteCron < time())
 * and tl_newsletter.sent is not yet set to true.
 */
#[AsCronJob('minutely')]
readonly class SendNewsletterCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InsertTagParser $insertTagParser,
        private readonly SimpleTokenParser $simpleTokenParser,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $projectDir,
        private readonly LoggerInterface|null $contaoErrorLogger,
        private readonly LoggerInterface|null $contaoCronLogger,
    ) {
    }

    public function __invoke(): void
    {
        $objNewsletter = $this->getNewsletter();

        if (null === $objNewsletter) {
            return;
        }

        $objNewsletterChannel = $objNewsletter->getRelated('pid');

        if (null === $objNewsletterChannel) {
            return;
        }

        $senderEmail = $objNewsletter->sender;

        if (!Validator::isEmail($senderEmail)) {
            throw new \Exception(sprintf('Invalid email address provided: %s', $senderEmail));
        }

        $senderName = $objNewsletter->senderName;

        if (empty($senderName)) {
            throw new \Exception(sprintf('Invalid email sender name provided: %s', $senderName));
        }

        $arrRecipients = $this->getRecipients((int) $objNewsletter->pid, (int) $objNewsletter->sendPerMinute);

        if (empty($arrRecipients)) {
            $objNewsletter->sent = '1';
            $objNewsletter->save();

            return;
        }

        $emailSubject = $objNewsletter->subject;
        $text = $this->insertTagParser->replaceInline($objNewsletter->text ?? '');
        $html = $this->insertTagParser->replaceInline($objNewsletter->content ?? '');

        // Convert relative URLs
        if ($objNewsletter->externalImages) {
            $html = Controller::convertRelativeUrls($html);
        }

        $mailerTransport = !empty($objNewsletter->mailerTransport) ? $objNewsletter->mailerTransport : $objNewsletterChannel->mailerTransport;

        $arrAttachments = $this->getAttachments($objNewsletter);

        foreach ($arrRecipients as $recipientEmail) {
            $objMember = MemberModel::findOneByEmail($recipientEmail);

            if (null !== $objMember) {
                $arrRecipient = $objMember->row();
            } else {
                $arrRecipient = [
                    'email' => $recipientEmail,
                ];
            }

            $objEmail = $this->generateEmailObject($objNewsletter, $senderEmail, $senderName, $emailSubject, $mailerTransport, $arrAttachments);

            if (true === $this->sendNewsletter($objEmail, $objNewsletter, $arrRecipient, $text, $html)) {
                $this->connection->executeStatement(
                    'DELETE FROM tl_newsletter_recipients WHERE pid = ? AND email LIKE ?',
                    [
                        $objNewsletterChannel->id,
                        $recipientEmail,
                    ]
                );

                // Log the newsletter sending to the file system
                $file = new File(sprintf('files/NEWSLETTER_%s_CRON_SUCCESS.txt', $objNewsletter->id));

                // Contao log
                $logMsg = sprintf(
                    'Newsletter with ID "%d" has been sent successfully by cron job to "%s"',
                    $objNewsletter->id,
                    Idna::decodeEmail($arrRecipient['email']),
                );
                $this->contaoCronLogger->info($logMsg);
            } else {
                $this->connection->executeStatement(
                    'UPDATE tl_newsletter_recipients SET active = ? WHERE pid = ? AND email LIKE ?',
                    [
                        '',
                        $objNewsletterChannel->id,
                        $recipientEmail,
                    ]
                );

                // Log the newsletter sending to the file system
                $file = new File(sprintf('files/NEWSLETTER_%s_CRON_FAILURE.txt', $objNewsletter->id));

                // Contao log
                $logMsg = sprintf(
                    'Newsletter with ID "%d" could not be sent by cron job to "%s"',
                    $objNewsletter->id,
                    Idna::decodeEmail($arrRecipient['email']),
                );
                $this->contaoErrorLogger->error($logMsg);
            }

            $file->append($objNewsletterChannel->id.';'.$recipientEmail.';'.date('Y_m_d_H:i'));
            $file->close();
        }
    }

    protected function generateEmailObject(NewsletterModel $objNewsletter, string $senderEmail, string $senderName, string $subject, string $mailerTransport = '', array $arrAttachments = []): Email
    {
        $objEmail = new Email();
        $objEmail->from = $senderEmail;
        $objEmail->fromName = $senderName;
        $objEmail->subject = $subject;
        $objEmail->embedImages = !$objNewsletter->externalImages;
        $objEmail->logFile = ContaoContext::NEWSLETTER.'_'.$objNewsletter->id;

        // Attachments
        if (!empty($arrAttachments)) {
            foreach ($arrAttachments as $strAttachment) {
                $objEmail->attachFile($this->projectDir.'/'.$strAttachment);
            }
        }

        // Add transport
        if (!empty($mailerTransport)) {
            $objEmail->addHeader('X-Transport', $objNewsletter->mailerTransport ?: $objNewsletter->channelMailerTransport);
        }

        return $objEmail;
    }

    private function getNewsletter(): NewsletterModel|null
    {
        return NewsletterModel::findOneBy(
            [
                'tl_newsletter.enableSendAndDeleteCron=? AND tl_newsletter.sent=? AND (tl_newsletter.cronJobStart=? OR tl_newsletter.cronJobStart<?)',
            ],
            [
                1,
                '',
                '',
                time(),
            ]
        );
    }

    private function getRecipients(int $intNewsletterChannel, int $intLimit): array
    {
        $strQuery = sprintf(
            'SELECT email FROM tl_newsletter_recipients WHERE active = ? AND pid = ? ORDER BY email LIMIT 0,%s',
            $intLimit,
        );

        $arrRecipients = $this->connection
            ->fetchFirstColumn(
                $strQuery,
                [
                    '1',
                    $intNewsletterChannel,
                ],
            )
        ;

        return !empty($arrRecipients) ? $arrRecipients : [];
    }

    private function sendNewsletter(Email $objEmail, NewsletterModel $objNewsletter, array $arrRecipient, string $text, string $html): bool
    {
        $hasFailure = false;

        // Prepare the text content
        $objEmail->text = $this->simpleTokenParser->parse($text, $arrRecipient);

        if (!$objNewsletter->sendText) {
            $objTemplate = new BackendTemplate($objNewsletter->template ?: 'mail_default');
            $objTemplate->setData($objNewsletter->row());
            $objTemplate->title = $objNewsletter->subject;
            $objTemplate->body = $this->simpleTokenParser->parse($html, $arrRecipient);
            $objTemplate->charset = System::getContainer()->getParameter('kernel.charset');
            $objTemplate->recipient = $arrRecipient['email'];

            // Parse template
            $objEmail->html = $objTemplate->parse();
            $objEmail->imageDir = $this->projectDir.'/';
        }

        $event = (new SendNewsletterEvent($arrRecipient['email'], $objEmail->text, $objEmail->html ?? ''))
            ->setHtmlAllowed(!$objNewsletter->sendText)
            ->setNewsletterData($objNewsletter->row())
            ->setRecipientData($arrRecipient)
        ;

        $this->eventDispatcher->dispatch($event);

        if ($event->isSkipSending()) {
            return false;
        }

        $objEmail->text = $event->getText();
        $objEmail->html = $event->isHtmlAllowed() ? $event->getHtml() : '';
        $arrRecipient = array_merge($event->getRecipientData(), ['email' => $event->getRecipientAddress()]);

        try {
            $objEmail->sendTo($arrRecipient['email']);
        } catch (RfcComplianceException|TransportException $e) {
            $hasFailure = true;
            $this->contaoErrorLogger->error(
                sprintf(
                    'Invalid recipient address "%s": %s',
                    Idna::decodeEmail($arrRecipient['email']),
                    $e->getMessage(),
                )
            );
        }

        // Rejected recipients
        if ($objEmail->hasFailures()) {
            $hasFailure = true;
        }

        return !$hasFailure;
    }

    private function getAttachments(NewsletterModel $objNewsletter): array
    {
        $arrAttachments = [];

        // Add attachments
        if ($objNewsletter->addFile) {
            $files = StringUtil::deserialize($objNewsletter->files);

            if (!empty($files) && \is_array($files)) {
                $objFiles = FilesModel::findMultipleByUuids($files);

                if (null !== $objFiles) {
                    while ($objFiles->next()) {
                        if (is_file($this->projectDir.'/'.$objFiles->path)) {
                            $arrAttachments[] = $objFiles->path;
                        }
                    }
                }
            }
        }

        return $arrAttachments;
    }
}
