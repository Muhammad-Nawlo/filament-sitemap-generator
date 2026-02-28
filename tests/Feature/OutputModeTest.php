<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithSitemapUrl;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
});

it('writes to file when output mode is file', function (): void {
    config()->set('filament-sitemap-generator.output', [
        'mode' => 'file',
        'file_path' => public_path('sitemap.xml'),
        'disk' => 'public',
        'disk_path' => 'sitemap.xml',
        'visibility' => 'public',
    ]);
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);
    syncSitemapSettingsToTestingDisk();

    app(SitemapGeneratorService::class)->generate();

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    expect(file_exists($path))->toBeTrue();
    $content = (string) file_get_contents($path);
    expect($content)->toContain('<urlset');
});

it('writes to disk when output mode is disk', function (): void {
    Storage::fake('public');
    config()->set('filament-sitemap-generator.output', [
        'mode' => 'disk',
        'file_path' => public_path('sitemap.xml'),
        'disk' => 'public',
        'disk_path' => 'sitemap.xml',
        'visibility' => 'public',
    ]);
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);
    syncSitemapSettingsToTestingDisk();

    app(SitemapGeneratorService::class)->generate();

    expect(Storage::disk('public')->exists('sitemap.xml'))->toBeTrue();
    $content = Storage::disk('public')->get('sitemap.xml');
    expect($content)->toContain('<urlset');
});

it('splits sitemap onto disk when exceeding max_urls_per_file', function (): void {
    Storage::fake('public');
    config()->set('filament-sitemap-generator.output', [
        'mode' => 'disk',
        'file_path' => public_path('sitemap.xml'),
        'disk' => 'public',
        'disk_path' => 'sitemap.xml',
        'visibility' => 'public',
    ]);
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithSitemapUrl::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);
    config()->set('filament-sitemap-generator.max_urls_per_file', 2);
    config()->set('filament-sitemap-generator.chunk_size', 10);
    syncSitemapSettingsToTestingDisk();

    TestPostWithSitemapUrl::factory()->count(5)->create();

    app(SitemapGeneratorService::class)->generate();

    expect(Storage::disk('public')->exists('sitemap.xml'))->toBeTrue();
    expect(Storage::disk('public')->exists('sitemap-1.xml'))->toBeTrue();
    $indexContent = Storage::disk('public')->get('sitemap.xml');
    expect($indexContent)->toContain('sitemapindex');
    expect($indexContent)->toContain('sitemap-1.xml');
});

it('clear removes disk files', function (): void {
    Storage::fake('public');
    config()->set('filament-sitemap-generator.output', [
        'mode' => 'disk',
        'file_path' => public_path('sitemap.xml'),
        'disk' => 'public',
        'disk_path' => 'sitemap.xml',
        'visibility' => 'public',
    ]);
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);
    syncSitemapSettingsToTestingDisk();

    $service = app(SitemapGeneratorService::class);
    $service->generate();
    expect(Storage::disk('public')->exists('sitemap.xml'))->toBeTrue();

    $cleared = $service->clear();
    expect($cleared)->toBeTrue();
    expect(Storage::disk('public')->exists('sitemap.xml'))->toBeFalse();
});
