<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class SitemapGeneratorService
{
    public function __construct(
        private readonly ?HttpClientFactory $http = null
    ) {}

    public function generate(): bool
    {
        $config = config('filament-sitemap-generator', []);
        $path = $config['path'] ?? public_path('sitemap.xml');
        $maxPerFile = (int) ($config['max_urls_per_file'] ?? 50000);
        if ($maxPerFile < 1) {
            $maxPerFile = 50000;
        }

        $partUrls = $this->buildStandardSitemaps($path, $config, $maxPerFile);

        if ($partUrls !== []) {
            $this->buildIndex($path, $partUrls);
        }

        if (! empty($config['news']['enabled'])) {
            $this->buildNewsSitemap($path, $config);
        }

        if (! empty($config['ping_search_engines']['enabled'])) {
            $this->pingSearchEngines($path, $config);
        }

        return true;
    }

    /**
     * @return list<string> Part URLs for index, or empty if single file written to path
     */
    private function buildStandardSitemaps(string $path, array $config, int $maxPerFile): array
    {
        $chunkSize = (int) ($config['chunk_size'] ?? 500);
        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        $dir = dirname($path);
        $baseUrl = $this->getBaseUrl();
        $partUrls = [];
        $partNumber = 1;
        $current = Sitemap::create();
        $count = 0;

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

        $addUrl = function (Url $tag) use (&$current, &$count, $maxPerFile, $flushPart): void {
            if ($count >= $maxPerFile) {
                $flushPart();
            }
            $current->add($tag);
            $count++;
        };

        $staticUrls = $config['static_urls'] ?? [];
        foreach ($staticUrls as $entry) {
            $url = $this->normalizeUrl($entry['url'] ?? '');
            $tag = Url::create($url);
            $this->applyPriorityAndChangefreq($tag, $entry);
            $this->applyStaticLastmod($tag, $entry);
            $addUrl($tag);
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
                        $addUrl($tag);
                    }
                }
            });
        }

        if ($partUrls === [] && $count > 0) {
            $current->writeToFile($path);

            return [];
        }

        if ($partUrls === [] && $count === 0) {
            return [];
        }

        if ($count > 0) {
            $filename = 'sitemap-' . $partNumber . '.xml';
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
            $current->writeToFile($filePath);
            $partUrls[] = $baseUrl . '/' . $filename;
        }

        return $partUrls;
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
     * @param  array{priority?: float, changefreq?: string, route?: string}  $options
     */
    private function resolveModelUrl(Model $model, array $options): ?string
    {
        if (method_exists($model, 'getSitemapUrl')) {
            $url = $model->getSitemapUrl();

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
        $base = config('filament-sitemap-generator.base_url') ?? config('app.url', '');

        return rtrim((string) $base, '/');
    }
}
