<?php

declare(strict_types=1);

use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
});

it('uses config path for output', function (): void {
    $customPath = storage_path('framework/testing/custom-sitemap.xml');
    $customDir = dirname($customPath);
    if (! is_dir($customDir)) {
        mkdir($customDir, 0755, true);
    }
    config()->set('filament-sitemap-generator.path', $customPath);
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);

    app(SitemapGeneratorService::class)->generate();

    expect(file_exists($customPath))->toBeTrue();
    @unlink($customPath);
});

it('uses config static_urls', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [
        ['url' => '/custom-page', 'priority' => 0.5, 'changefreq' => 'yearly'],
    ]);
    config()->set('filament-sitemap-generator.models', []);

    app(SitemapGeneratorService::class)->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('custom-page');
    expect($content)->toContain('<priority>0.5</priority>');
    expect($content)->toContain('<changefreq>yearly</changefreq>');
});
