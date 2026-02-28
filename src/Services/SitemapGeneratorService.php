<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class SitemapGeneratorService
{
    public function __construct(
        private readonly ?HttpClientFactory $http = null,
        private readonly ?SitemapSettingsResolver $resolver = null
    ) {}

    private function getResolver(): SitemapSettingsResolver
    {
        return $this->resolver ?? app(SitemapSettingsResolver::class);
    }

    /**
     * @return array{path: string, static_urls: array, models: array, chunk_size: int, max_urls_per_file: int, base_url: string|null, news: array, ping_search_engines: array, enable_index_sitemap: bool}
     */
    private function getConfig(): array
    {
        return $this->getResolver()->resolve();
    }

    public function generate(): SitemapRun
    {
        $start = microtime(true);
        $config = $this->getConfig();
        $path = $config['path'];
        $modelClass = $this->getSitemapRunModelClass();

        try {
            $maxPerFile = $config['max_urls_per_file'];
            if ($maxPerFile < 1) {
                $maxPerFile = 50000;
            }

            [$partUrls, $staticUrlsCount, $modelUrlsCount] = $this->buildStandardSitemaps($path, $config, $maxPerFile);
            $totalUrls = $staticUrlsCount + $modelUrlsCount;

            if ($partUrls !== []) {
                $this->buildIndex($path, $partUrls);
            }

            if (! empty($config['news']['enabled'])) {
                $this->buildNewsSitemap($path, $config);
            }

            if (! empty($config['ping_search_engines']['enabled'])) {
                $this->pingSearchEngines($path, $config);
            }

            $fileSize = file_exists($path) ? (int) filesize($path) : 0;
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            return $modelClass::create([
                'generated_at' => now(),
                'total_urls' => $totalUrls,
                'static_urls' => $staticUrlsCount,
                'model_urls' => $modelUrlsCount,
                'file_size' => $fileSize,
                'duration_ms' => $durationMs,
                'status' => 'success',
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            return $modelClass::create([
                'generated_at' => now(),
                'total_urls' => 0,
                'static_urls' => 0,
                'model_urls' => 0,
                'file_size' => 0,
                'duration_ms' => $durationMs,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return class-string<SitemapRun>
     */
    private function getSitemapRunModelClass(): string
    {
        $class = config('filament-sitemap-generator.sitemap_run_model', SitemapRun::class);

        return is_string($class) && is_a($class, SitemapRun::class, true) ? $class : SitemapRun::class;
    }

    public function clear(): bool
    {
        $path = $this->getSitemapPath();
        $dir = dirname($path);
        $removed = false;

        if (file_exists($path)) {
            @unlink($path);
            $removed = true;
        }

        $pattern = $dir . DIRECTORY_SEPARATOR . 'sitemap-*.xml';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
                $removed = true;
            }
        }

        $newsPath = $dir . DIRECTORY_SEPARATOR . 'sitemap-news.xml';
        if (file_exists($newsPath)) {
            @unlink($newsPath);
            $removed = true;
        }

        return $removed;
    }

    public function getSitemapPath(): string
    {
        return $this->getConfig()['path'];
    }

    /**
     * Validate sitemap file. Memory-safe: reads up to 2MB for validation.
     *
     * @return array{status: 'valid'|'invalid'|'missing', message: string|null}
     */
    public function validateSitemap(?string $path = null): array
    {
        $path ??= $this->getSitemapPath();

        if (! file_exists($path)) {
            return ['status' => 'missing', 'message' => __('filament-sitemap-generator::page.validation_missing')];
        }

        $maxBytes = 2 * 1024 * 1024;
        $content = @file_get_contents($path, false, null, 0, $maxBytes);
        if ($content === false) {
            return ['status' => 'invalid', 'message' => __('filament-sitemap-generator::page.validation_read_failed')];
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $ok = $doc->loadXML($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($ok) {
            return ['status' => 'valid', 'message' => null];
        }

        $message = implode("\n", array_map(fn (\LibXMLError $e): string => trim($e->message), $errors));

        return ['status' => 'invalid', 'message' => $message ?: __('filament-sitemap-generator::page.validation_invalid')];
    }

    /**
     * Read sitemap content for preview. Memory-safe: max 1MB, pretty-printed.
     *
     * @return array{exists: bool, content: string|null, file_size: int, last_modified: \DateTimeInterface|null, validation: array{status: string, message: string|null}}
     */
    public function getPreviewData(?string $path = null): array
    {
        $path ??= $this->getSitemapPath();
        $exists = file_exists($path);
        $fileSize = $exists ? (int) filesize($path) : 0;
        $lastModified = null;
        if ($exists && ($mtime = @filemtime($path)) !== false) {
            $lastModified = new \DateTimeImmutable('@' . $mtime);
        }
        $validation = $this->validateSitemap($path);

        $content = null;
        if ($exists) {
            $maxBytes = 1024 * 1024;
            $raw = @file_get_contents($path, false, null, 0, $maxBytes);
            if ($raw !== false) {
                $doc = new \DOMDocument;
                $doc->preserveWhiteSpace = false;
                $doc->formatOutput = true;
                if (@$doc->loadXML($raw)) {
                    $content = $doc->saveXML();
                } else {
                    $content = $raw;
                }
            }
        }

        return [
            'exists' => $exists,
            'content' => $content,
            'file_size' => $fileSize,
            'last_modified' => $lastModified,
            'validation' => $validation,
        ];
    }

    /**
     * @return array{0: list<string>, 1: int, 2: int} [partUrls, staticCount, modelCount]
     */
    private function buildStandardSitemaps(string $path, array $config, int $maxPerFile): array
    {
        $chunkSize = $config['chunk_size'];
        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        $dir = dirname($path);
        $baseUrl = $this->getBaseUrl();
        $partUrls = [];
        $partNumber = 1;
        $current = Sitemap::create();
        $count = 0;
        $staticCount = 0;
        $modelCount = 0;

        $flushPart = function () use (&$current, &$count, &$partNumber, &$partUrls, $dir, $baseUrl): void {
            if ($count === 0) {
                return;
            }
            $filename = 'sitemap-' . $partNumber . '.xml';
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
            $current->writeToFile($filePath);
            $partUrls[] = $baseUrl . '/' . $filename;
            $partNumber++;
            $current = Sitemap::create();
            $count = 0;
        };

        $addUrl = function (Url $tag, string $source = 'static') use (&$current, &$count, &$staticCount, &$modelCount, $maxPerFile, $flushPart): void {
            if ($count >= $maxPerFile) {
                $flushPart();
            }
            $current->add($tag);
            $count++;
            if ($source === 'static') {
                $staticCount++;
            } else {
                $modelCount++;
            }
        };

        $staticUrls = $config['static_urls'] ?? [];
        foreach ($staticUrls as $entry) {
            $url = $this->normalizeUrl($entry['url'] ?? '');
            $tag = Url::create($url);
            $this->applyPriorityAndChangefreq($tag, $entry);
            $this->applyStaticLastmod($tag, $entry);
            $addUrl($tag, 'static');
        }

        $models = $config['models'] ?? [];
        foreach ($models as $modelClass => $options) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }
            $modelClass::query()->chunk($chunkSize, function ($records) use ($options, $addUrl): void {
                foreach ($records as $record) {
                    $tag = $this->buildModelUrlTag($record, $options);
                    if ($tag !== null) {
                        $addUrl($tag, 'model');
                    }
                }
            });
        }

        if ($partUrls === [] && $count > 0) {
            $current->writeToFile($path);

            return [[], $staticCount, $modelCount];
        }

        if ($partUrls === [] && $count === 0) {
            return [[], $staticCount, $modelCount];
        }

        if ($count > 0) {
            $filename = 'sitemap-' . $partNumber . '.xml';
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
            $current->writeToFile($filePath);
            $partUrls[] = $baseUrl . '/' . $filename;
        }

        return [$partUrls, $staticCount, $modelCount];
    }

    /**
     * @param  list<string>  $partUrls
     */
    private function buildIndex(string $path, array $partUrls): void
    {
        $index = SitemapIndex::create();
        foreach ($partUrls as $url) {
            $index->add($url);
        }
        $index->writeToFile($path);
    }

    private function buildNewsSitemap(string $mainPath, array $config): void
    {
        $newsConfig = $config['news'] ?? [];
        $models = $newsConfig['models'] ?? [];
        $name = (string) ($newsConfig['publication_name'] ?? '');
        $language = (string) ($newsConfig['publication_language'] ?? 'en');
        if ($name === '' || $models === []) {
            return;
        }

        $cutoff = Carbon::now()->subHours(48);
        $sitemap = Sitemap::create();
        $dir = dirname($mainPath);
        $newsPath = $dir . DIRECTORY_SEPARATOR . 'sitemap-news.xml';

        foreach ($models as $modelClass) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }
            $modelClass::query()->chunk(500, function ($records) use ($sitemap, $name, $language, $cutoff): void {
                foreach ($records as $record) {
                    $this->addNewsUrl($sitemap, $record, $name, $language, $cutoff);
                }
            });
        }

        $tags = $sitemap->getTags();
        if ($tags !== []) {
            $sitemap->writeToFile($newsPath);
        }
    }

    private function addNewsUrl(Sitemap $sitemap, Model $record, string $publicationName, string $publicationLanguage, Carbon $cutoff): void
    {
        $pubDate = $this->resolveNewsPublicationDate($record);
        if ($pubDate === null || $pubDate < $cutoff) {
            return;
        }
        $url = $this->resolveNewsRecordUrl($record);
        if ($url === null || $url === '') {
            return;
        }
        $title = method_exists($record, 'getSitemapNewsTitle') ? (string) $record->getSitemapNewsTitle() : (string) ($record->title ?? $record->name ?? '');
        $tag = Url::create($url);
        $tag->addNews($publicationName, $publicationLanguage, $title, $pubDate);
        $sitemap->add($tag);
    }

    private function resolveNewsRecordUrl(Model $record): ?string
    {
        if (method_exists($record, 'getSitemapUrl')) {
            $url = $record->getSitemapUrl();

            return is_string($url) ? $this->normalizeUrl($url) : null;
        }

        return null;
    }

    private function resolveNewsPublicationDate(Model $record): ?DateTimeInterface
    {
        if (method_exists($record, 'getSitemapNewsPublicationDate')) {
            $date = $record->getSitemapNewsPublicationDate();

            return $date instanceof DateTimeInterface ? $date : null;
        }
        $date = $record->published_at ?? $record->updated_at ?? $record->created_at ?? null;

        return $date instanceof DateTimeInterface ? $date : null;
    }

    private function pingSearchEngines(string $path, array $config): void
    {
        $baseUrl = rtrim($this->getBaseUrl(), '/');
        $basename = basename($path);
        $sitemapUrl = $baseUrl . '/' . $basename;

        $engines = $config['ping_search_engines']['engines'] ?? [];
        $pingUrls = [
            'google' => 'https://www.google.com/ping?sitemap=' . rawurlencode($sitemapUrl),
            'bing' => 'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemapUrl),
        ];

        $factory = $this->http ?? app(HttpClientFactory::class);
        foreach ($engines as $engine) {
            $engine = is_string($engine) ? strtolower($engine) : '';
            $url = $pingUrls[$engine] ?? null;
            if ($url !== null) {
                try {
                    $factory->get($url);
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * @param  array{url: string, priority?: float, changefreq?: string, lastmod?: DateTimeInterface|null}  $entry
     */
    private function applyStaticLastmod(Url $tag, array $entry): void
    {
        $lastmod = $entry['lastmod'] ?? null;
        if ($lastmod instanceof DateTimeInterface) {
            $tag->setLastModificationDate($lastmod);
        }
    }

    /**
     * @param  array{priority?: float, changefreq?: string, route?: string}  $options
     */
    private function buildModelUrlTag(Model $record, array $options): ?Url
    {
        $url = $this->resolveModelUrl($record, $options);
        if ($url === null || $url === '') {
            return null;
        }
        $tag = Url::create($url);
        $this->applyPriorityAndChangefreq($tag, $options);
        $this->applyModelLastmod($tag, $record);
        $this->applyAlternateUrls($tag, $record);
        $this->applySitemapImages($tag, $record);

        return $tag;
    }

    private function applyModelLastmod(Url $tag, Model $record): void
    {
        if (method_exists($record, 'getSitemapLastModified')) {
            $date = $record->getSitemapLastModified();
            if ($date instanceof DateTimeInterface) {
                $tag->setLastModificationDate($date);
            }

            return;
        }
        if (isset($record->updated_at) && $record->updated_at !== null && $record->updated_at instanceof DateTimeInterface) {
            $tag->setLastModificationDate($record->updated_at);
        }
    }

    private function applyAlternateUrls(Url $tag, Model $record): void
    {
        if (! method_exists($record, 'getAlternateUrls')) {
            return;
        }
        $alternates = $record->getAlternateUrls();
        if (! is_array($alternates)) {
            return;
        }
        foreach ($alternates as $locale => $url) {
            if (is_string($url) && $url !== '') {
                $tag->addAlternate($this->normalizeUrl($url), (string) $locale);
            }
        }
    }

    private function applySitemapImages(Url $tag, Model $record): void
    {
        if (! method_exists($record, 'getSitemapImages')) {
            return;
        }
        $images = $record->getSitemapImages();
        if (! is_array($images)) {
            return;
        }
        foreach ($images as $item) {
            $url = $item['url'] ?? '';
            if (is_string($url) && $url !== '') {
                $caption = (string) ($item['caption'] ?? '');
                $tag->addImage($this->normalizeUrl($url), $caption);
            }
        }
    }

    /**
     * @param  array{priority?: float, changefreq?: string, route?: string, url_resolver_method?: string}  $options
     */
    private function resolveModelUrl(Model $model, array $options): ?string
    {
        $method = $options['url_resolver_method'] ?? 'getSitemapUrl';
        if (is_string($method) && method_exists($model, $method)) {
            $url = $model->{$method}();

            return is_string($url) ? $this->normalizeUrl($url) : null;
        }
        $routeName = $options['route'] ?? null;
        if ($routeName === null || $routeName === '') {
            return null;
        }

        try {
            $url = route($routeName, $model);

            return $this->normalizeUrl($url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{priority?: float, changefreq?: string}  $options
     */
    private function applyPriorityAndChangefreq(Url $tag, array $options): void
    {
        if (isset($options['priority'])) {
            $tag->setPriority((float) $options['priority']);
        }
        if (isset($options['changefreq']) && is_string($options['changefreq'])) {
            $tag->setChangeFrequency($options['changefreq']);
        }
    }

    private function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $base = $this->getBaseUrl();
        $path = ltrim($url, '/');

        return $path === '' ? rtrim($base, '/') : rtrim($base, '/') . '/' . $path;
    }

    private function getBaseUrl(): string
    {
        $config = $this->getConfig();
        $base = $config['base_url'] ?? config('app.url', '');

        return rtrim((string) $base, '/');
    }
}
