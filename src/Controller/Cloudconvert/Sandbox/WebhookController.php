<?php

// phpcs:ignoreFile
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

namespace Markocupic\SacEventToolBundle\Controller\Cloudconvert\Sandbox;

use CloudConvert\CloudConvert;
use CloudConvert\Exceptions\SignatureVerificationException;
use CloudConvert\Exceptions\UnexpectedDataException;
use CloudConvert\Models\Task;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_cloudconvert/sandbox/webhook', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        #[Autowire('%markocupic_cloudconvert.sandbox_api_key%')]
        private readonly string $sandboxApiKey,
    ) {
    }

    public function __invoke(): Response
    {
        $this->framework->initialize();

        $cloudconvert = new CloudConvert([
            'api_key' => $this->sandboxApiKey,
            'sandbox' => true,
        ]);

        // You can find it in your webhook settings
        $signingSecret = 'QP5oNVeRenM4GmlAEyuIE1EfEbMuOQqe';

        $payload = @file_get_contents('php://input');
        $signature = $_SERVER['HTTP_CLOUDCONVERT_SIGNATURE'];

        try {
            $webhookEvent = $cloudconvert->webhookHandler()->constructEvent($payload, $signature, $signingSecret);
        } catch (UnexpectedDataException $e) {
            // Invalid payload
            return new Response('Unexpected data exception', 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return new Response('Signature verification exception', 400);
        }

        $job = $webhookEvent->getJob();

        // can be used to store an ID
        $tag = $job->getTag();

        $exportTask = $job->getTasks()
            ->whereStatus(Task::STATUS_FINISHED) // get the task with 'finished' status ...
            ->whereName('export-my-file')[0]; // ... and with the name 'export-it'

        return new Response('all ok');
    }
}
