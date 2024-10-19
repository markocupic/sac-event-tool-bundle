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

namespace Markocupic\SacEventToolBundle\Messenger\MessageHandler;

use Contao\FilesModel;
use Contao\MemberModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\ContaoFrontendUserNotification\Model\FrontendUserNotificationModel;
use Markocupic\ContaoFrontendUserNotification\Notification\DefaultFrontendUserNotification;
use Markocupic\SacEventToolBundle\Controller\PrintTourList\DownloadController;
use Markocupic\SacEventToolBundle\DocxTemplator\TourListGenerator;
use Markocupic\SacEventToolBundle\Messenger\Message\GenerateTourListBookletMessage;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsMessageHandler]
readonly class GenerateTourListBookletHandler
{
    public function __construct(
        private Connection $connection,
        private RouterInterface $router,
        private TourListGenerator $tourListGenerator,
        private UriSigner $uriSigner,
    ) {
    }

    public function __invoke(GenerateTourListBookletMessage $message): void
    {
        $ids = $message->getIds();
        $user = $message->getUser();
        $filename = $message->getFilename();
        $outputFormat = $message->getOutputFormat();
        $memberModel = MemberModel::findByPk($user->id);
        $notificationType = 'personal-tour-list-ready-for-download';
        $endOfLifeTstamp = time() + 7 * 24 * 3600;

        if ($filesModel = $this->generateBooklet($ids, $outputFormat)) {
            try {
                $this->connection->beginTransaction();

                $notificationModel = (new DefaultFrontendUserNotification($user, $notificationType, '', '', $endOfLifeTstamp))->getModel();
                $notificationModel->messageTitle = $this->generateMessageTitle($memberModel);
                $notificationModel->messageText = $this->generateMessageText($notificationModel, $filesModel, $filename);
                $notificationModel->tourlistSRC = $filesModel->uuid;
                $notificationModel->save();

                $this->connection->commit();
            } catch (\Exception $e) {
                $this->connection->rollBack();

                throw new \Exception($e->getMessage());
            }
        }
    }

    private function generateMessageTitle(MemberModel $memberModel): string
    {
        $text = sprintf('Hallo %s!', $memberModel->firstname);

        return $this->revertInputEncoding($text);
    }

    private function generateMessageText(FrontendUserNotificationModel $notificationModel, FilesModel $filesModel, string $filename): string
    {
        $url = $this->router->generate(DownloadController::class, [
            'file' => base64_encode($filesModel->getAbsolutePath()),
            'filename' => base64_encode($filename),
            'notificationId' => $notificationModel->id,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        if (str_starts_with($url, 'https://sac')) {
            $url = str_replace('https://', 'https://www.', $url);
        }

        $urlSigned = $this->uriSigner->sign($url);

        $text = sprintf('<div class="lh-lg mb-3 small">Du kannst dein persönliches Tourenprogramm jetzt herunterladen:<br><a href="%s" title="Download starten">%s</a></div>', $urlSigned, $filename);

        return $this->revertInputEncoding($text);
    }

    private function generateBooklet(array $arrIds, string $outputFormat): FilesModel|bool
    {
        try {
            // Get event ids from request
            $arrIds = array_map('intval', $arrIds);

            return $this->tourListGenerator->generate($arrIds, $outputFormat);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function revertInputEncoding(string $text): string
    {
        return StringUtil::revertInputEncoding($text);
    }
}
