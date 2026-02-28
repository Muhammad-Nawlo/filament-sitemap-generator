<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
});

it('uses config path for output', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);
    syncSitemapSettingsToTestingDisk();

    app(SitemapGeneratorService::class)->generate();

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    expect(file_exists($path))->toBeTrue();
});

it('uses config static_urls', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [
        ['url' => '/custom-page', 'priority' => 0.5, 'changefreq' => 'yearly'],
    ]);
    config()->set('filament-sitemap-generator.models', []);
    syncSitemapSettingsToTestingDisk();

    app(SitemapGeneratorService::class)->generate();

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('custom-page');
    expect($content)->toContain('<priority>0.5</priority>');
    expect($content)->toContain('<changefreq>yearly</changefreq>');
});
