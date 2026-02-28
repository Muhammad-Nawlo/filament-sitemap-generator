<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestNewsPost;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', []);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
    config()->set('filament-sitemap-generator.news', [
        'enabled' => true,
        'publication_name' => 'Test Publication',
        'publication_language' => 'en',
        'models' => [TestNewsPost::class],
    ]);
    syncSitemapSettingsToTestingDisk();
});

function newsSitemapPath(): string
{
    $dir = dirname(Storage::disk('sitemap_testing')->path('sitemap.xml'));

    return $dir . DIRECTORY_SEPARATOR . 'sitemap-news.xml';
}

it('only includes models within last 48 hours in news sitemap', function (): void {
    $recent = TestNewsPost::factory()->create([
        'title' => 'Recent',
        'published_at' => now()->subHours(24),
    ]);
    $old = TestNewsPost::factory()->create([
        'title' => 'Old',
        'published_at' => now()->subHours(49),
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    expect(file_exists(newsSitemapPath()))->toBeTrue();

    $content = (string) file_get_contents(newsSitemapPath());
    expect($content)->toContain('/posts/' . $recent->id);
    expect($content)->not->toContain('/posts/' . $old->id);
});

it('creates news sitemap file when enabled', function (): void {
    TestNewsPost::factory()->create([
        'title' => 'News item',
        'published_at' => now()->subHour(),
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    expect(file_exists(newsSitemapPath()))->toBeTrue();
});

it('does not create news sitemap when disabled', function (): void {
    config()->set('filament-sitemap-generator.news.enabled', false);
    syncSitemapSettingsToTestingDisk();
    TestNewsPost::factory()->create([
        'title' => 'News item',
        'published_at' => now()->subHour(),
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    expect(file_exists(newsSitemapPath()))->toBeFalse();
});

it('applies publication name and language to news sitemap', function (): void {
    config()->set('filament-sitemap-generator.news.publication_name', 'My News Site');
    config()->set('filament-sitemap-generator.news.publication_language', 'fr');

    TestNewsPost::factory()->create([
        'title' => 'News title',
        'published_at' => now()->subHour(),
    ]);

    $service = app(SitemapGeneratorService::class);
    $service->generate();

    $content = (string) file_get_contents(newsSitemapPath());
    expect($content)->toContain('My News Site');
    expect($content)->toContain('fr');
    expect($content)->toContain('News title');
});
