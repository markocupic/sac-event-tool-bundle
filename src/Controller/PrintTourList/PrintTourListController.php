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

namespace Markocupic\SacEventToolBundle\Controller\PrintTourList;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Contao\MemberModel;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\DocxTemplator\TourListGenerator;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PrintTourListController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly TourListGenerator $tourListGenerator,
        private string $projectDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    #[Route('/_print_tour_list/download_status/{uuid}', name: self::class.'_get_download_status', defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function getDownloadStatusAction(Request $request, string $uuid): JsonResponse
    {
        $json = ['status' => 'busy'];

        if (is_file($this->projectDir.'/system/tmp/.'.base64_encode($uuid))) {
            $json = ['status' => 'ready'];
        }

        return new JsonResponse($json);
    }

    /**
     * Download tour list as docx/pdf booklet.
     */
    #[Route('/_print_tour_list/download', name: self::class.'_download', defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function downloadAction(Request $request): Response
    {
        $outputFormat = 'pdf';

        // Get event ids from request
        $arrIds = array_map('intval', explode(',', $request->query->get('ids', '')));

        $splFileObject = $this->tourListGenerator->generate($arrIds, $outputFormat);

        $filename = 'Meine_Tourenliste.'.$outputFormat;

        // The file is required to find out with ajax requests whether the download has already been completed.
        // With this method, we can disable the download button as long as the download is running.
        file_put_contents(
            $this->projectDir.'/system/tmp/.'.base64_encode($request->query->get('uuid')),
            'done',
        );

        $username = 'Anonymous';

        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $userModel = MemberModel::findByPk($user->id);
            $username = sprintf('%s %s [%d]', $userModel->firstname, $userModel->lastname, $userModel->sacMemberId);
        }

        $message = sprintf('%s has downloaded the tour list booklet ("%s").', $username, $filename);

        // Log download
        $this->contaoGeneralLogger->info(
            $message,
            ['contao' => new ContaoContext(__METHOD__, Log::DOWNLOAD_TOUR_LIST_BOOKLET)],
        );

        return $this->binaryFileDownload->sendFileToBrowser($splFileObject->getRealPath(), $filename);
    }
}
