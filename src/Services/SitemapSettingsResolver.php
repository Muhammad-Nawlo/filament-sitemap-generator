<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Services;

use Illuminate\Support\Facades\Schema;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;

/**
 * Resolves sitemap configuration from database (SitemapSetting) with config file fallback.
 */
class SitemapSettingsResolver
{
    /**
     * Get effective config array for the generator. Uses DB settings when available, else config().
     *
     * @return array{path: string, static_urls: array, models: array, chunk_size: int, max_urls_per_file: int, base_url: string|null, news: array, ping_search_engines: array, enable_index_sitemap: bool}
     */
    public function resolve(): array
    {
        $config = config('filament-sitemap-generator', []);

        if (! $this->settingsTableExists()) {
            return $this->normalizeConfig($config);
        }

        try {
            $settings = SitemapSetting::getSettings();
        } catch (\Throwable) {
            return $this->normalizeConfig($config);
        }

        $path = $settings->getStorageFullPath();
        $staticUrls = $this->resolveStaticUrls($settings, $config);
        $models = $this->resolveModels($settings, $config);
        $chunkSize = $settings->chunk_size >= 100 ? $settings->chunk_size : ($config['chunk_size'] ?? 1000);
        $maxPerFile = (int) ($config['max_urls_per_file'] ?? 50000);
        if ($maxPerFile < 1) {
            $maxPerFile = 50000;
        }

        return [
            'path' => $path,
            'static_urls' => $staticUrls,
            'models' => $models,
            'chunk_size' => $chunkSize,
            'max_urls_per_file' => $maxPerFile,
            'base_url' => $config['base_url'] ?? null,
            'news' => $config['news'] ?? [],
            'ping_search_engines' => $config['ping_search_engines'] ?? [],
            'enable_index_sitemap' => $settings->enable_index_sitemap,
        ];
    }

    private function settingsTableExists(): bool
    {
        $table = config('filament-sitemap-generator.sitemap_settings_table', 'sitemap_settings');

        return Schema::hasTable($table);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $path = $config['path'] ?? public_path('sitemap.xml');
        $staticUrls = $config['static_urls'] ?? [];
        $models = $config['models'] ?? [];
        $chunkSize = (int) ($config['chunk_size'] ?? 500);
        if ($chunkSize < 1) {
            $chunkSize = 500;
        }
        $maxPerFile = (int) ($config['max_urls_per_file'] ?? 50000);
        if ($maxPerFile < 1) {
            $maxPerFile = 50000;
        }

        return [
            'path' => $path,
            'static_urls' => $staticUrls,
            'models' => $models,
            'chunk_size' => $chunkSize,
            'max_urls_per_file' => $maxPerFile,
            'base_url' => $config['base_url'] ?? null,
            'news' => $config['news'] ?? [],
            'ping_search_engines' => $config['ping_search_engines'] ?? [],
            'enable_index_sitemap' => false,
        ];
    }

    /**
     * @return list<array{url: string, priority?: float, changefreq?: string}>
     */
    private function resolveStaticUrls(SitemapSetting $settings, array $config): array
    {
        $urls = $settings->static_urls;

        if (! is_array($urls)) {
            return $config['static_urls'] ?? [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']];
        }

        $defaultFreq = $settings->default_change_frequency ?? 'weekly';
        $defaultPriority = $settings->default_priority !== null ? (float) $settings->default_priority : 0.8;

        $out = [];
        foreach ($urls as $entry) {
            if (! is_array($entry) || empty($entry['url'])) {
                continue;
            }
            $out[] = [
                'url' => (string) $entry['url'],
                'priority' => isset($entry['priority']) ? (float) $entry['priority'] : $defaultPriority,
                'changefreq' => $entry['changefreq'] ?? $defaultFreq,
            ];
        }

        return $out;
    }

    /**
     * Convert settings models array to generator format: modelClass => [priority, changefreq, route?, url_resolver_method?]
     *
     * @return array<string, array{priority?: float, changefreq?: string, route?: string, url_resolver_method?: string}>
     */
    private function resolveModels(SitemapSetting $settings, array $config): array
    {
        $models = $settings->models;

        if (! is_array($models)) {
            return $config['models'] ?? [];
        }

        $defaultFreq = $settings->default_change_frequency ?? 'weekly';
        $defaultPriority = $settings->default_priority !== null ? (float) $settings->default_priority : 0.8;

        $out = [];
        foreach ($models as $entry) {
            if (! is_array($entry) || empty($entry['model_class'])) {
                continue;
            }
            if (isset($entry['enabled']) && ! $entry['enabled']) {
                continue;
            }
            $class = (string) $entry['model_class'];
            $out[$class] = [
                'priority' => isset($entry['priority']) ? (float) $entry['priority'] : $defaultPriority,
                'changefreq' => $entry['changefreq'] ?? $defaultFreq,
                'url_resolver_method' => $entry['url_resolver_method'] ?? 'getSitemapUrl',
            ];
        }

        return $out;
    }
}
