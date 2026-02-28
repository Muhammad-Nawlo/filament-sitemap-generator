<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Commands;

use Illuminate\Console\Command;
use MuhammadNawlo\FilamentSitemapGenerator\Jobs\GenerateSitemapJob;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'filament-sitemap-generator:generate';

    protected $description;

    public function __construct(
        private readonly SitemapGeneratorService $sitemapGenerator
    ) {
        parent::__construct();
        $this->description = __('filament-sitemap-generator::command.description');
    }

    public function handle(): int
    {
        if ($this->isQueueEnabled()) {
            $this->dispatchJob();
            $this->info(__('filament-sitemap-generator::command.dispatched'));

            return self::SUCCESS;
        }

        return $this->runSynchronously();
    }

    private function isQueueEnabled(): bool
    {
        $queue = config('filament-sitemap-generator.queue', []);

        return ! empty($queue['enabled']);
    }

    private function dispatchJob(): void
    {
        $job = new GenerateSitemapJob;
        $connection = config('filament-sitemap-generator.queue.connection');
        $queue = config('filament-sitemap-generator.queue.queue');

        if ($connection !== null && $connection !== '') {
            $job->onConnection((string) $connection);
        }
        if ($queue !== null && $queue !== '') {
            $job->onQueue((string) $queue);
        }

        dispatch($job);
    }

    private function runSynchronously(): int
    {
        try {
            $this->sitemapGenerator->generate();
            $this->info(__('filament-sitemap-generator::command.success'));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error(__('filament-sitemap-generator::command.failed') . ': ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
