<?php

declare(strict_types=1);

use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithAlternates;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithImages;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithLastmod;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithSitemapUrl;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.static_urls', [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ]);
    config()->set('filament-sitemap-generator.models', []);
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
});

it('generates a single sitemap.xml when under limit', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
        ['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly'],
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    expect($path)->toBeString();
    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);
    expect($content)->toContain('<loc>');
    expect($content)->toContain('</loc>');
    expect($content)->toContain('/about');
    expect(count(glob(dirname($path) . DIRECTORY_SEPARATOR . 'sitemap-*.xml')))->toBe(0);
});

it('generates multiple files when exceeding max_urls_per_file', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithSitemapUrl::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);
    config()->set('filament-sitemap-generator.max_urls_per_file', 2);
    config()->set('filament-sitemap-generator.chunk_size', 10);

    TestPostWithSitemapUrl::factory()->count(5)->create();

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $dir = dirname((string) config('filament-sitemap-generator.path'));
    $partFiles = glob($dir . DIRECTORY_SEPARATOR . 'sitemap-*.xml');
    expect($partFiles)->not->toBeEmpty();
    expect(count($partFiles))->toBeGreaterThanOrEqual(2);
});

it('generates sitemap index correctly', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithSitemapUrl::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);
    config()->set('filament-sitemap-generator.max_urls_per_file', 2);
    config()->set('filament-sitemap-generator.chunk_size', 10);

    TestPostWithSitemapUrl::factory()->count(5)->create();

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);
    expect($content)->toContain('sitemapindex');
    expect($content)->toContain('sitemap-');
});

it('applies lastmod when available', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithLastmod::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);

    $post = TestPostWithLastmod::factory()->create([
        'title' => 'Lastmod post',
        'updated_at' => now()->subDay(),
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('lastmod');
    expect($content)->toContain('/posts/' . $post->id);
});

it('applies priority and changefreq', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [
        ['url' => '/', 'priority' => 0.9, 'changefreq' => 'hourly'],
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('<priority>0.9</priority>');
    expect($content)->toContain('<changefreq>hourly</changefreq>');
});

it('attaches alternates when model implements getAlternateUrls', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithAlternates::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);

    TestPostWithAlternates::factory()->create(['title' => 'Alternates post']);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('xhtml:link');
    expect($content)->toContain('alternate');
});

it('attaches images when model implements getSitemapImages', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithImages::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);

    TestPostWithImages::factory()->create(['title' => 'Image post']);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('image:image');
    expect($content)->toContain('.jpg');
});

it('respects base_url override', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.base_url', 'https://custom.example.com');

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('https://custom.example.com');
});

it('respects chunk_size', function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', [
        TestPostWithSitemapUrl::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);
    config()->set('filament-sitemap-generator.chunk_size', 1);

    TestPostWithSitemapUrl::factory()->count(3)->create();

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $path = config('filament-sitemap-generator.path');
    expect(file_exists($path))->toBeTrue();
    $content = (string) file_get_contents($path);
    expect(substr_count($content, '<url>'))->toBe(3);
});
