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

use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Messenger\Message\GenerateTourListBookletMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/_print_tour_list/init_download', name: self::class, defaults: ['_scope' => 'frontend', '_token_check' => false])]
class InitDownloadController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
    ) {
    }

    /**
     * Download tour list as docx/pdf booklet.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return $this->json(['success' => false]);
        }

        // Get event ids from request
        $ids = array_map('intval', explode(',', $request->query->get('ids', '')));
        $outputFormat = 'pdf';
        $filename = 'Mein persönliches Tourenprogramm.'.$outputFormat;

        $this->messageBus->dispatch(GenerateTourListBookletMessage::create($ids, $outputFormat, $filename, $user));

        return $this->json(['success' => true]);
    }
}
