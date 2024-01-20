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

namespace Markocupic\SacEventToolBundle\Download;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\UnicodeString;

class BinaryFileDownload
{
    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     */
    public function sendFileToBrowser(string $filePath, string $fileName = '', bool $inline = false, bool $deleteFileAfterSend = false): BinaryFileResponse
    {
        $response = new BinaryFileResponse($filePath);

        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName,
            (new UnicodeString(basename($filePath)))->ascii()->toString(),
        );

        $response->deleteFileAfterSend($deleteFileAfterSend);

        return $response;
    }
}
