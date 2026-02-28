<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use MuhammadNawlo\FilamentSitemapGenerator\Jobs\GenerateSitemapJob;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostThatThrows;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.queue.enabled', false);
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    config()->set('filament-sitemap-generator.models', []);
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
});

it('runs command successfully synchronously', function (): void {
    $exitCode = $this->artisan('filament-sitemap-generator:generate')->run();

    expect($exitCode)->toBe(0);
});

it('returns exit code 0 on success', function (): void {
    $this->artisan('filament-sitemap-generator:generate')
        ->assertSuccessful();
});

it('generates file when command runs', function (): void {
    syncSitemapSettingsToTestingDisk();

    $this->artisan('filament-sitemap-generator:generate')->run();

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    expect(file_exists($path))->toBeTrue();
});

it('returns exit code 1 on failure when generation throws', function (): void {
    config()->set('filament-sitemap-generator.models', [
        TestPostThatThrows::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ]);

    $exitCode = $this->artisan('filament-sitemap-generator:generate')->run();

    expect($exitCode)->toBe(1);
});

it('dispatches GenerateSitemapJob when queue enabled and does not run service synchronously', function (): void {
    Queue::fake();

    config()->set('filament-sitemap-generator.queue.enabled', true);

    $this->artisan('filament-sitemap-generator:generate')->run();

    Queue::assertPushed(GenerateSitemapJob::class);

    $path = config('filament-sitemap-generator.path');
    expect(file_exists($path ?? ''))->toBeFalse();
});

it('does not dispatch job when queue disabled', function (): void {
    Queue::fake();

    config()->set('filament-sitemap-generator.queue.enabled', false);

    $this->artisan('filament-sitemap-generator:generate')->run();

    Queue::assertNotPushed(GenerateSitemapJob::class);
});
