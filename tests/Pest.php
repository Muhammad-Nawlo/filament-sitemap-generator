<?php

use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.path', storage_path('framework/testing/sitemap.xml'));
});

/**
 * Sync SitemapSetting to use the sitemap_testing disk so resolved path matches tests' expected path.
 */
function syncSitemapSettingsToTestingDisk(): void
{
    $settings = SitemapSetting::getSettings();
    $settings->storage_path = 'sitemap_testing';
    $settings->filename = 'sitemap.xml';
    $settings->static_urls = config('filament-sitemap-generator.static_urls', []);
    $modelsConfig = config('filament-sitemap-generator.models', []);
    $models = [];
    foreach ($modelsConfig as $modelClass => $opts) {
        $models[] = [
            'model_class' => $modelClass,
            'url_resolver_method' => $opts['url_resolver_method'] ?? 'getSitemapUrl',
            'changefreq' => $opts['changefreq'] ?? null,
            'priority' => $opts['priority'] ?? null,
            'enabled' => true,
        ];
    }
    $settings->models = $models;
    $settings->save();
}
