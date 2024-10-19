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

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_print_tour_list/download/{file}/{filename}/{notificationId}', name: self::class, defaults: ['_scope' => 'frontend', '_token_check' => false])]
class DownloadController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function __invoke(Request $request, string $file, string $filename, int $notificationId): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return new Response('Invalid request');
        }

        $file = base64_decode($file, true);
        $filename = base64_decode($filename, true);

        return $this->binaryFileDownload->sendFileToBrowser($file, $filename);
    }
}
