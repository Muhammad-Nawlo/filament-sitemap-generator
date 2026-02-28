<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class GenerateSitemapJob implements ShouldQueue
{
    public function handle(SitemapGeneratorService $sitemapGenerator): void
    {
        $sitemapGenerator->generate();
    }
}
