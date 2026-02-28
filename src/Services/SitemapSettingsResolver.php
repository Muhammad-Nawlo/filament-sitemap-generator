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
     * @return array{path: string, output: array{mode: string, file_path: string, disk: string, disk_path: string, visibility: string}, static_urls: array, models: array, chunk_size: int, max_urls_per_file: int, base_url: string|null, news: array, ping_search_engines: array, enable_index_sitemap: bool}
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
        $output = $this->resolveOutputWithSettings($settings, $config, $path);
        if (($output['mode'] ?? 'file') === 'file') {
            $output['file_path'] = $path;
        }
        $staticUrls = $this->resolveStaticUrls($settings, $config);
        $models = $this->resolveModels($settings, $config);
        $chunkSize = $settings->chunk_size >= 100 ? $settings->chunk_size : ($config['chunk_size'] ?? 1000);
        $maxPerFile = (int) ($config['max_urls_per_file'] ?? 50000);
        if ($maxPerFile < 1) {
            $maxPerFile = 50000;
        }

        $resolvedPath = $output['mode'] === 'disk'
            ? $output['disk_path']
            : $output['file_path'];

        return [
            'path' => $resolvedPath,
            'output' => $output,
            'static_urls' => $staticUrls,
            'models' => $models,
            'chunk_size' => $chunkSize,
            'max_urls_per_file' => $maxPerFile,
            'base_url' => $config['base_url'] ?? null,
            'news' => $config['news'] ?? [],
            'ping_search_engines' => $config['ping_search_engines'] ?? [],
            'enable_index_sitemap' => $settings->enable_index_sitemap,
            'crawl' => $this->resolveCrawlWithSettings($settings, $config),
        ];
    }

    /**
     * Merge DB output settings with config. DB overrides; null falls back to config.
     *
     * @param  array<string, mixed>  $config
     * @return array{mode: string, file_path: string, disk: string, disk_path: string, visibility: string}
     */
    private function resolveOutputWithSettings(SitemapSetting $settings, array $config, string $legacyPath): array
    {
        $table = $settings->getTable();
        $configOutput = $config['output'] ?? [];
        $base = $this->resolveOutput($config, $legacyPath);

        if (! Schema::hasColumn($table, 'output_mode')) {
            return $base;
        }

        $mode = $settings->output_mode;
        if ($mode === 'disk' || $mode === 'file') {
            $base['mode'] = $mode;
        }
        if ($mode === 'file') {
            $fp = $settings->file_path;
            $base['file_path'] = is_string($fp) && $fp !== '' ? $fp : ($configOutput['file_path'] ?? $legacyPath);
        }
        if ($mode === 'disk') {
            $disk = $settings->disk;
            $base['disk'] = is_string($disk) && $disk !== '' ? $disk : (string) ($configOutput['disk'] ?? 'public');
            $dp = $settings->disk_path;
            $base['disk_path'] = is_string($dp) && $dp !== '' ? $dp : (string) ($configOutput['disk_path'] ?? 'sitemap.xml');
            $vis = $settings->visibility;
            $base['visibility'] = is_string($vis) && $vis !== '' ? $vis : (string) ($configOutput['visibility'] ?? 'public');
        }

        return $base;
    }

    /**
     * Merge DB crawl settings with config. DB overrides; null falls back to config.
     *
     * @param  array<string, mixed>  $config
     * @return array{enabled: bool, url: string|null, concurrency: int, max_count: int|null, max_tags_per_sitemap: int, exclude_patterns: array<int, string>, maximum_depth: int|null, crawl_profile: string|null, should_crawl: string|null, has_crawled: string|null, execute_javascript: bool, chrome_binary_path: string|null, node_binary_path: string|null}
     */
    private function resolveCrawlWithSettings(SitemapSetting $settings, array $config): array
    {
        $crawl = $this->resolveCrawl($config);
        $table = $settings->getTable();

        if (Schema::hasColumn($table, 'crawl_enabled')) {
            if ($settings->crawl_enabled !== null) {
                $crawl['enabled'] = (bool) $settings->crawl_enabled;
            }
        }
        if (Schema::hasColumn($table, 'crawl_url')) {
            if ($settings->crawl_url !== null && $settings->crawl_url !== '') {
                $crawl['url'] = $settings->crawl_url;
            }
        }
        if (Schema::hasColumn($table, 'concurrency') && $settings->concurrency !== null) {
            $crawl['concurrency'] = (int) $settings->concurrency;
        }
        if (Schema::hasColumn($table, 'max_count')) {
            $crawl['max_count'] = $settings->max_count !== null ? (int) $settings->max_count : null;
        }
        if (Schema::hasColumn($table, 'maximum_depth')) {
            $crawl['maximum_depth'] = $settings->maximum_depth !== null ? (int) $settings->maximum_depth : null;
        }
        if (Schema::hasColumn($table, 'exclude_patterns') && is_array($settings->exclude_patterns)) {
            $crawl['exclude_patterns'] = array_values(array_filter($settings->exclude_patterns, fn ($p): bool => is_string($p) && $p !== ''));
        }
        if (Schema::hasColumn($table, 'crawl_profile') && $settings->crawl_profile !== null && $settings->crawl_profile !== '') {
            $crawl['crawl_profile'] = $settings->crawl_profile;
        }
        if (Schema::hasColumn($table, 'should_crawl') && $settings->should_crawl !== null && $settings->should_crawl !== '') {
            $crawl['should_crawl'] = $settings->should_crawl;
        }
        if (Schema::hasColumn($table, 'has_crawled') && $settings->has_crawled !== null && $settings->has_crawled !== '') {
            $crawl['has_crawled'] = $settings->has_crawled;
        }
        if (Schema::hasColumn($table, 'execute_javascript') && $settings->execute_javascript !== null) {
            $crawl['execute_javascript'] = (bool) $settings->execute_javascript;
        }
        if (Schema::hasColumn($table, 'chrome_binary_path') && $settings->chrome_binary_path !== null && $settings->chrome_binary_path !== '') {
            $crawl['chrome_binary_path'] = $settings->chrome_binary_path;
        }
        if (Schema::hasColumn($table, 'node_binary_path') && $settings->node_binary_path !== null && $settings->node_binary_path !== '') {
            $crawl['node_binary_path'] = $settings->node_binary_path;
        }

        return $crawl;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{mode: string, file_path: string, disk: string, disk_path: string, visibility: string}
     */
    private function resolveOutput(array $config, string $legacyPath): array
    {
        $output = $config['output'] ?? [];
        $mode = isset($output['mode']) && $output['mode'] === 'disk' ? 'disk' : 'file';
        $filePath = (string) ($output['file_path'] ?? $legacyPath);
        if ($filePath === '' && $mode === 'file') {
            $filePath = (string) ($config['path'] ?? $legacyPath);
        }

        return [
            'mode' => $mode,
            'file_path' => $filePath,
            'disk' => (string) ($output['disk'] ?? 'public'),
            'disk_path' => (string) ($output['disk_path'] ?? 'sitemap.xml'),
            'visibility' => (string) ($output['visibility'] ?? 'public'),
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
        $output = $this->resolveOutput($config, $path);
        $resolvedPath = $output['mode'] === 'disk' ? $output['disk_path'] : $output['file_path'];
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
            'path' => $resolvedPath,
            'output' => $output,
            'static_urls' => $staticUrls,
            'models' => $models,
            'chunk_size' => $chunkSize,
            'max_urls_per_file' => $maxPerFile,
            'base_url' => $config['base_url'] ?? null,
            'news' => $config['news'] ?? [],
            'ping_search_engines' => $config['ping_search_engines'] ?? [],
            'enable_index_sitemap' => false,
            'crawl' => $this->resolveCrawl($config),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{enabled: bool, url: string|null, concurrency: int, max_count: int|null, max_tags_per_sitemap: int, exclude_patterns: array<int, string>, maximum_depth: int|null, crawl_profile: string|null, should_crawl: string|null, has_crawled: string|null, execute_javascript: bool, chrome_binary_path: string|null, node_binary_path: string|null}
     */
    private function resolveCrawl(array $config): array
    {
        $crawl = $config['crawl'] ?? [];
        $exclude = $crawl['exclude_patterns'] ?? [];
        if (! is_array($exclude)) {
            $exclude = [];
        }
        $maximumDepth = isset($crawl['maximum_depth']) && $crawl['maximum_depth'] !== null
            ? (int) $crawl['maximum_depth'] : null;
        $crawlProfile = isset($crawl['crawl_profile']) && is_string($crawl['crawl_profile']) && $crawl['crawl_profile'] !== ''
            ? $crawl['crawl_profile'] : null;
        $shouldCrawl = isset($crawl['should_crawl']) && is_string($crawl['should_crawl']) && $crawl['should_crawl'] !== ''
            ? $crawl['should_crawl'] : null;
        $hasCrawled = isset($crawl['has_crawled']) && is_string($crawl['has_crawled']) && $crawl['has_crawled'] !== ''
            ? $crawl['has_crawled'] : null;
        $executeJavascript = (bool) ($crawl['execute_javascript'] ?? false);
        $chromePath = isset($crawl['chrome_binary_path']) && is_string($crawl['chrome_binary_path']) && $crawl['chrome_binary_path'] !== ''
            ? $crawl['chrome_binary_path'] : null;
        $nodePath = isset($crawl['node_binary_path']) && is_string($crawl['node_binary_path']) && $crawl['node_binary_path'] !== ''
            ? $crawl['node_binary_path'] : null;

        return [
            'enabled' => (bool) ($crawl['enabled'] ?? false),
            'url' => isset($crawl['url']) && is_string($crawl['url']) && $crawl['url'] !== '' ? $crawl['url'] : null,
            'concurrency' => (int) ($crawl['concurrency'] ?? 10),
            'max_count' => array_key_exists('max_count', $crawl) ? (is_int($crawl['max_count']) ? $crawl['max_count'] : null) : null,
            'max_tags_per_sitemap' => (int) ($crawl['max_tags_per_sitemap'] ?? 50000),
            'exclude_patterns' => array_values(array_filter($exclude, fn ($p): bool => is_string($p) && $p !== '')),
            'maximum_depth' => $maximumDepth,
            'crawl_profile' => $crawlProfile,
            'should_crawl' => $shouldCrawl,
            'has_crawled' => $hasCrawled,
            'execute_javascript' => $executeJavascript,
            'chrome_binary_path' => $chromePath,
            'node_binary_path' => $nodePath,
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
