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

namespace Markocupic\SacEventToolBundle\Image;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;

/**
 * Rotates an image with Imagick (ext-imagick is required, see composer.json).
 *
 * Throws an exception if the parameters are invalid or the rotation fails.
 */
class RotateImage
{
    // Same formats as Contao\File::isGdImage, but checked by the file content (MIME type) instead of the extension
    private const array IMAGE_MIME_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/heic',
        'image/jxl',
    ];

    /**
     * @param string $sourcePath absolute path of the image
     * @param string $targetPath absolute path of the rotated image; empty: overwrite the source image
     *
     * @throws \InvalidArgumentException if the source file does not exist or is not an image
     * @throws \RuntimeException         if the rotation fails
     */
    public function rotate(string $sourcePath, int $angle = 90, string $targetPath = ''): void
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" not found.', $sourcePath));
        }

        if (!$this->isImage($sourcePath)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" is not an image (allowed: %s).', $sourcePath, implode(', ', self::IMAGE_MIME_TYPES)));
        }

        if ('' === $targetPath) {
            $targetPath = $sourcePath;
        } else {
            // Create the target folder if it does not exist
            (new Filesystem())->mkdir(Path::getDirectory($targetPath));
        }

        try {
            $imagick = new \Imagick($sourcePath);

            try {
                $imagick->rotateImage(new \ImagickPixel('none'), $angle);
                $imagick->writeImage($targetPath);
            } finally {
                $imagick->clear();
            }
        } catch (\ImagickException $e) {
            throw new \RuntimeException(\sprintf('Could not rotate the image "%s": %s', $sourcePath, $e->getMessage()), 0, $e);
        }
    }

    public function isImage(string $path): bool
    {
        return \in_array(MimeTypes::getDefault()->guessMimeType($path), self::IMAGE_MIME_TYPES, true);
    }
}
