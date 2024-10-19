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

namespace Markocupic\SacEventToolBundle\Controller\Cloudconvert\Sandbox;

use CloudConvert\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_cloudconvert/sandbox/job', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class JobController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        #[Autowire('%markocupic_cloudconvert.sandbox_api_key%')]
        private readonly string $sandboxApiKey,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): Response
    {
        $this->framework->initialize();

        //die(md5_file($this->projectDir. '/files/file.docx'));

        $splFile = new \SplFileObject($this->projectDir.'/files/file.docx');
        $this->upload($splFile);

        return new Response('An new conversion job has been sent to Cloudconvert!'.$output);
    }

    private function upload(\SplFileObject $splFile): void
    {
        $apiKey = $this->sandboxApiKey;

        $cloudconvert = new CloudConvert([
            'api_key' => $apiKey,
            'sandbox' => true,
        ]);

        $job = (new Job())
            ->addTask(new Task('import/upload', 'upload-my-file'))
            ->addTask(
                (new Task('convert', 'convert-my-file'))
                    ->set('input', 'upload-my-file')
                    ->set('output_format', 'pdf')
            )
            ->addTask(
                (new Task('export/url', 'export-my-file'))
                    ->set('input', 'convert-my-file')
            )
        ;

        $job = $cloudconvert->jobs()->create($job);

        $uploadTask = $job->getTasks()->whereName('upload-my-file')[0];

        $cloudconvert->tasks()->upload($uploadTask, fopen($splFile->getRealPath(), 'r'), $splFile->getBasename());
    }
}
