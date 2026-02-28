<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapSettingsResolver;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapGenerator;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class SitemapGeneratorService
{
    /** @var array<string, true> URLs already added (for deduplication) */
    protected array $seenUrls = [];

    /** Used during buildStandardSitemaps for splitting / appendCrawledUrls */
    private ?Sitemap $currentSitemap = null;

    private int $currentSitemapCount = 0;

    private int $currentPartNumber = 1;

    /** @var list<string> */
    private array $currentPartUrls = [];

    private string $sitemapPathForBuild = '';

    private string $sitemapDirForBuild = '';

    private bool $isFileModeForBuild = true;

    private int $maxUrlsPerFileForBuild = 50000;

    private int $staticCountForRun = 0;

    private int $modelCountForRun = 0;

    private int $crawlCountForRun = 0;

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

            [$partUrls, $staticUrlsCount, $modelUrlsCount, $crawledUrlsCount] = $this->buildStandardSitemaps($path, $config, $maxPerFile);
            $totalUrls = $staticUrlsCount + $modelUrlsCount + $crawledUrlsCount;

            if ($partUrls !== []) {
                $this->buildIndex($path, $partUrls);
            }

            if (! empty($config['news']['enabled'])) {
                $this->buildNewsSitemap($path, $config);
            }

            if (! empty($config['ping_search_engines']['enabled'])) {
                $this->pingSearchEngines($path, $config);
            }

            $fileSize = $this->getOutputFileSize($path, $config);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            return $modelClass::create([
                'generated_at' => now(),
                'total_urls' => $totalUrls,
                'static_urls' => $staticUrlsCount,
                'model_urls' => $modelUrlsCount,
                'crawled_urls' => $crawledUrlsCount,
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
                'crawled_urls' => 0,
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
        $config = $this->getConfig();
        $path = $config['path'];
        $output = $config['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';

        if ($mode === 'disk') {
            $disk = $output['disk'] ?? 'public';
            $diskPath = $output['disk_path'] ?? 'sitemap.xml';
            $dir = dirname($diskPath);
            $dirPrefix = $dir !== '.' ? rtrim($dir, '/') . '/' : '';
            $toDelete = [];
            if (Storage::disk($disk)->exists($diskPath)) {
                $toDelete[] = $diskPath;
            }
            $toDelete[] = $dirPrefix . 'sitemap-news.xml';
            $files = Storage::disk($disk)->files($dir !== '.' ? $dir : '');
            foreach ($files as $file) {
                if (preg_match('#^sitemap-\d+\.xml$#', basename($file)) || basename($file) === 'sitemap-news.xml') {
                    $toDelete[] = $file;
                }
            }
            $toDelete = array_unique($toDelete);
            $deleted = 0;
            foreach ($toDelete as $f) {
                if (Storage::disk($disk)->exists($f)) {
                    Storage::disk($disk)->delete($f);
                    $deleted++;
                }
            }

            return $deleted > 0;
        }

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
     * Write XML content to output (file or disk). Path is full filesystem path when mode is 'file', or disk-relative path when mode is 'disk'.
     */
    private function writeXml(string $path, string $xmlContent): void
    {
        $output = $this->getConfig()['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';

        if ($mode === 'disk') {
            $disk = $output['disk'] ?? 'public';
            $visibility = $output['visibility'] ?? 'public';
            Storage::disk($disk)->put($path, $xmlContent, $visibility);

            return;
        }

        file_put_contents($path, $xmlContent);
    }

    /**
     * Resolve public URL for a relative path (e.g. sitemap.xml, sitemap-1.xml). Used for sitemap index entries and ping.
     */
    private function resolvePublicUrl(string $relativePath): string
    {
        $output = $this->getConfig()['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';
        $baseUrl = rtrim($this->getBaseUrl(), '/');

        if ($mode === 'file') {
            return $baseUrl . '/' . basename($relativePath);
        }

        return $baseUrl . '/' . ltrim($relativePath, '/');
    }

    /**
     * Get file size for the main sitemap path (for SitemapRun). Works for both file and disk mode.
     */
    private function getOutputFileSize(string $path, array $config): int
    {
        $output = $config['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';

        if ($mode === 'disk') {
            $disk = $output['disk'] ?? 'public';
            if (Storage::disk($disk)->exists($path)) {
                return (int) Storage::disk($disk)->size($path);
            }

            return 0;
        }

        return file_exists($path) ? (int) filesize($path) : 0;
    }

    /**
     * Validate sitemap file. Memory-safe: reads up to 2MB for validation.
     *
     * @return array{status: 'valid'|'invalid'|'missing', message: string|null}
     */
    public function validateSitemap(?string $path = null): array
    {
        $path ??= $this->getSitemapPath();
        $config = $this->getConfig();
        $output = $config['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';

        if ($mode === 'disk') {
            $disk = $output['disk'] ?? 'public';
            if (! Storage::disk($disk)->exists($path)) {
                return ['status' => 'missing', 'message' => __('filament-sitemap-generator::page.validation_missing')];
            }
            $content = Storage::disk($disk)->get($path);
            $content = $content !== null ? substr($content, 0, 2 * 1024 * 1024) : '';
        } else {
            if (! file_exists($path)) {
                return ['status' => 'missing', 'message' => __('filament-sitemap-generator::page.validation_missing')];
            }
            $maxBytes = 2 * 1024 * 1024;
            $content = @file_get_contents($path, false, null, 0, $maxBytes);
            if ($content === false) {
                return ['status' => 'invalid', 'message' => __('filament-sitemap-generator::page.validation_read_failed')];
            }
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
        $config = $this->getConfig();
        $output = $config['output'] ?? ['mode' => 'file'];
        $mode = $output['mode'] ?? 'file';

        if ($mode === 'disk') {
            $disk = $output['disk'] ?? 'public';
            $exists = Storage::disk($disk)->exists($path);
            $fileSize = 0;
            $lastModified = null;
            $raw = null;
            if ($exists) {
                $fileSize = (int) Storage::disk($disk)->size($path);
                $raw = Storage::disk($disk)->get($path);
                $lastModified = Storage::disk($disk)->lastModified($path);
                $lastModified = $lastModified ? new \DateTimeImmutable('@' . $lastModified) : null;
            }
        } else {
            $exists = file_exists($path);
            $fileSize = $exists ? (int) filesize($path) : 0;
            $lastModified = null;
            if ($exists && ($mtime = @filemtime($path)) !== false) {
                $lastModified = new \DateTimeImmutable('@' . $mtime);
            }
            $raw = $exists ? @file_get_contents($path, false, null, 0, 1024 * 1024) : false;
        }

        $validation = $this->validateSitemap($path);

        $content = null;
        if ($raw !== null && $raw !== false) {
            $doc = new \DOMDocument;
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            if (@$doc->loadXML($raw)) {
                $content = $doc->saveXML();
            } else {
                $content = $raw;
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
     * @return array{0: list<string>, 1: int, 2: int, 3: int} [partUrls, staticCount, modelCount, crawlCount]
     */
    private function buildStandardSitemaps(string $path, array $config, int $maxPerFile): array
    {
        $this->seenUrls = [];
        $chunkSize = $config['chunk_size'];
        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        $output = $config['output'] ?? ['mode' => 'file'];
        $isFileMode = ($output['mode'] ?? 'file') === 'file';
        $dir = $isFileMode ? dirname($path) : '';

        $this->currentSitemap = Sitemap::create();
        $this->currentSitemapCount = 0;
        $this->currentPartNumber = 1;
        $this->currentPartUrls = [];
        $this->sitemapPathForBuild = $path;
        $this->sitemapDirForBuild = $dir;
        $this->isFileModeForBuild = $isFileMode;
        $this->maxUrlsPerFileForBuild = $maxPerFile;
        $this->staticCountForRun = 0;
        $this->modelCountForRun = 0;
        $this->crawlCountForRun = 0;

        $addUrl = function (Url $tag, string $source): void {
            $this->addTagToCurrentSitemap($tag, $source);
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
                    if ($record instanceof Sitemapable) {
                        $this->addSitemapableRecordToSitemap($record, $addUrl);
                    } else {
                        $tag = $this->buildModelUrlTag($record, $options);
                        if ($tag !== null) {
                            $addUrl($tag, 'model');
                        }
                    }
                }
            });
        }

        $crawl = $config['crawl'] ?? [];
        if (! empty($crawl['enabled']) && isset($crawl['url']) && $crawl['url'] !== '') {
            $this->appendCrawledUrls($config);
        }

        $partUrls = $this->currentPartUrls;
        $staticCount = $this->staticCountForRun;
        $modelCount = $this->modelCountForRun;
        $crawlCount = $this->crawlCountForRun;

        if ($partUrls === [] && $this->currentSitemapCount > 0) {
            $xml = $this->currentSitemap->render();
            $this->writeXml($path, $xml);

            return [[], $staticCount, $modelCount, $crawlCount];
        }

        if ($partUrls === [] && $this->currentSitemapCount === 0) {
            return [[], $staticCount, $modelCount, $crawlCount];
        }

        if ($this->currentSitemapCount > 0) {
            $this->flushCurrentPart();
            $partUrls = $this->currentPartUrls;
        }

        return [$partUrls, $staticCount, $modelCount, $crawlCount];
    }

    /**
     * Add a URL tag to the current sitemap (or next part). Deduplicates by URL. Used by static, model, and crawl.
     */
    private function addTagToCurrentSitemap(Url $tag, string $source): void
    {
        $loc = (string) $tag->url;
        if ($loc === '' || isset($this->seenUrls[$loc])) {
            return;
        }
        $this->seenUrls[$loc] = true;

        if ($this->currentSitemapCount >= $this->maxUrlsPerFileForBuild) {
            $this->flushCurrentPart();
        }
        $this->currentSitemap->add($tag);
        $this->currentSitemapCount++;

        if ($source === 'static') {
            $this->staticCountForRun++;
        } elseif ($source === 'model') {
            $this->modelCountForRun++;
        } elseif ($source === 'crawl') {
            $this->crawlCountForRun++;
        }
    }

    private function flushCurrentPart(): void
    {
        if ($this->currentSitemap === null || $this->currentSitemapCount === 0) {
            return;
        }
        $filename = 'sitemap-' . $this->currentPartNumber . '.xml';
        $writePath = $this->isFileModeForBuild
            ? $this->sitemapDirForBuild . DIRECTORY_SEPARATOR . $filename
            : $filename;
        $xml = $this->currentSitemap->render();
        $this->writeXml($writePath, $xml);
        $this->currentPartUrls[] = $this->resolvePublicUrl($filename);
        $this->currentPartNumber++;
        $this->currentSitemap = Sitemap::create();
        $this->currentSitemapCount = 0;
    }

    private function appendCrawledUrls(array $config): void
    {
        $crawl = $config['crawl'] ?? [];
        $url = $crawl['url'] ?? '';
        if ($url === '') {
            return;
        }
        $maxCount = $crawl['max_count'] ?? null;
        if ($maxCount === null) {
            $maxCount = 1000;
        }
        $excludePatterns = $crawl['exclude_patterns'] ?? [];
        $executeJs = (bool) ($crawl['execute_javascript'] ?? false);
        if ($executeJs && ! class_exists(\Spatie\Browsershot\Browsershot::class)) {
            Log::warning('Filament Sitemap Generator: JavaScript crawl disabled. Require spatie/browsershot, Node.js, and Chrome/Chromium.', [
                'requirement' => 'spatie/browsershot',
            ]);
            $executeJs = false;
        }

        $originalCrawlProfile = config('sitemap.crawl_profile');
        $originalExecuteJs = config('sitemap.execute_javascript');
        $originalChromePath = config('sitemap.chrome_binary_path');
        $originalNodePath = config('sitemap.node_binary_path');

        try {
            if (! empty($crawl['crawl_profile'])) {
                config(['sitemap.crawl_profile' => $crawl['crawl_profile']]);
            }

            if ($executeJs) {
                config(['sitemap.execute_javascript' => true]);
                if (! empty($crawl['chrome_binary_path'])) {
                    config(['sitemap.chrome_binary_path' => $crawl['chrome_binary_path']]);
                }
                if (! empty($crawl['node_binary_path'])) {
                    config(['sitemap.node_binary_path' => $crawl['node_binary_path']]);
                }
                Log::info('Sitemap crawl running with JavaScript execution enabled.');
            }

            $crawledSitemap = null;
            for ($attempt = 0; $attempt <= 1; $attempt++) {
                if ($attempt === 1 && $executeJs) {
                    Log::info('Filament Sitemap Generator: retrying crawl without JavaScript.');
                    config(['sitemap.execute_javascript' => false]);
                }
                try {
                    $generator = $this->buildCrawlGenerator($url, $crawl);
                    $crawledSitemap = $generator->getSitemap();
                    break;
                } catch (\Throwable $e) {
                    Log::error('Filament Sitemap Generator: crawl failed.', [
                        'url' => $url,
                        'message' => $e->getMessage(),
                    ]);
                    if ($attempt === 0 && $executeJs) {
                        continue;
                    }
                    break;
                }
            }

            if ($crawledSitemap !== null) {
                foreach ($crawledSitemap->getTags() as $tag) {
                    if (! $tag instanceof Url) {
                        continue;
                    }
                    $loc = (string) $tag->url;
                    if ($this->urlMatchesExcludePatterns($loc, $excludePatterns)) {
                        continue;
                    }
                    $this->addTagToCurrentSitemap($tag, 'crawl');
                }
            }
        } finally {
            config(['sitemap.crawl_profile' => $originalCrawlProfile]);
            config(['sitemap.execute_javascript' => $originalExecuteJs]);
            config(['sitemap.chrome_binary_path' => $originalChromePath]);
            config(['sitemap.node_binary_path' => $originalNodePath]);
        }
    }

    /**
     * Build and configure SitemapGenerator for crawl. Does not run getSitemap().
     *
     * @param  array<string, mixed>  $crawl
     */
    private function buildCrawlGenerator(string $url, array $crawl): SitemapGenerator
    {
        $maxCount = $crawl['max_count'] ?? null;
        if ($maxCount === null) {
            $maxCount = 1000;
        }
        $generator = SitemapGenerator::create($url)
            ->setConcurrency((int) ($crawl['concurrency'] ?? 10))
            ->setMaximumCrawlCount($maxCount)
            ->maxTagsPerSitemap((int) ($crawl['max_tags_per_sitemap'] ?? 50000));

        if (isset($crawl['maximum_depth']) && $crawl['maximum_depth'] !== null) {
            $depth = (int) $crawl['maximum_depth'];
            $generator->configureCrawler(function ($crawler) use ($depth): void {
                $crawler->setMaximumDepth($depth);
            });
        }

        $shouldCrawlCallable = $this->resolveInvokable((string) ($crawl['should_crawl'] ?? ''));
        if ($shouldCrawlCallable !== null) {
            $generator->shouldCrawl($shouldCrawlCallable);
        } elseif (! empty($crawl['should_crawl'])) {
            Log::warning('Filament Sitemap Generator: should_crawl class invalid or not invokable.', [
                'class' => $crawl['should_crawl'],
            ]);
        }

        $hasCrawledCallable = $this->resolveInvokable((string) ($crawl['has_crawled'] ?? ''));
        if ($hasCrawledCallable !== null) {
            $generator->hasCrawled($hasCrawledCallable);
        } elseif (! empty($crawl['has_crawled'])) {
            Log::warning('Filament Sitemap Generator: has_crawled class invalid or not invokable.', [
                'class' => $crawl['has_crawled'],
            ]);
        }

        return $generator;
    }

    /**
     * Resolve a class from the container as an invokable callable. Returns null if class does not exist or is not callable.
     */
    private function resolveInvokable(string $class): ?callable
    {
        if ($class === '' || ! class_exists($class)) {
            return null;
        }
        try {
            $instance = app($class);
            if (! is_callable($instance)) {
                return null;
            }

            return $instance;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $patterns  Wildcard patterns (Str::is)
     */
    private function urlMatchesExcludePatterns(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a Sitemapable record's tag(s) to the sitemap via the addUrl callback.
     * Handles single Url|string or iterable (array/Traversable) return from toSitemapTag().
     *
     * @param  callable(Url, string): void  $addUrl
     */
    private function addSitemapableRecordToSitemap(Sitemapable $record, callable $addUrl): void
    {
        $result = $record->toSitemapTag();

        if (is_array($result) || $result instanceof \Traversable) {
            foreach ($result as $tag) {
                $urlTag = $this->normalizeSitemapTagToUrl($tag);
                if ($urlTag !== null) {
                    $addUrl($urlTag, 'model');
                }
            }

            return;
        }

        $urlTag = $this->normalizeSitemapTagToUrl($result);
        if ($urlTag !== null) {
            $addUrl($urlTag, 'model');
        }
    }

    /**
     * Convert a single tag from toSitemapTag() (Url|string) to Url for adding, or null to skip.
     */
    private function normalizeSitemapTagToUrl(mixed $tag): ?Url
    {
        if ($tag instanceof Url) {
            return $tag;
        }
        if (is_string($tag) && trim($tag) !== '') {
            return Url::create($this->normalizeUrl($tag));
        }

        return null;
    }

    /**
     * @param  list<string>  $partUrls  Public URLs for each part sitemap
     */
    private function buildIndex(string $path, array $partUrls): void
    {
        $index = SitemapIndex::create();
        foreach ($partUrls as $url) {
            $index->add($url);
        }
        $xml = $index->render();
        $this->writeXml($path, $xml);
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

        $output = $config['output'] ?? ['mode' => 'file'];
        $isFileMode = ($output['mode'] ?? 'file') === 'file';
        $newsPath = $isFileMode
            ? dirname($mainPath) . DIRECTORY_SEPARATOR . 'sitemap-news.xml'
            : 'sitemap-news.xml';

        $cutoff = Carbon::now()->subHours(48);
        $sitemap = Sitemap::create();

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
            $xml = $sitemap->render();
            $this->writeXml($newsPath, $xml);
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
        $sitemapUrl = $this->resolvePublicUrl($path);

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
        $this->applySitemapVideos($tag, $record);

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

    private function applySitemapVideos(Url $tag, Model $record): void
    {
        if (! method_exists($record, 'getSitemapVideos')) {
            return;
        }

        $videos = $record->getSitemapVideos();
        if (! is_array($videos)) {
            return;
        }

        foreach ($videos as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $thumbnailLoc = $entry['thumbnail_loc'] ?? '';
            $title = $entry['title'] ?? '';
            $description = $entry['description'] ?? '';
            $contentLoc = isset($entry['content_loc']) && is_string($entry['content_loc']) ? $entry['content_loc'] : null;
            $playerLoc = isset($entry['player_loc']) && (is_string($entry['player_loc']) || is_array($entry['player_loc'])) ? $entry['player_loc'] : null;

            if ($thumbnailLoc === '' || $title === '' || $description === '') {
                continue;
            }
            if ($contentLoc === null && $playerLoc === null) {
                continue;
            }

            $options = $this->extractVideoOptions($entry);
            $allow = is_array($entry['allow'] ?? null) ? $entry['allow'] : [];
            $deny = is_array($entry['deny'] ?? null) ? $entry['deny'] : [];
            $tags = is_array($entry['tags'] ?? null) ? $entry['tags'] : [];

            try {
                $tag->addVideo(
                    $this->normalizeUrl((string) $thumbnailLoc),
                    $title,
                    $description,
                    $contentLoc !== null ? $this->normalizeUrl($contentLoc) : null,
                    $playerLoc,
                    $options,
                    $allow,
                    $deny,
                    $tags
                );
            } catch (\Throwable) {
                // Skip invalid video entry; do not break sitemap generation
            }
        }
    }

    /**
     * Extract optional video keys into the options array for Spatie's addVideo().
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function extractVideoOptions(array $entry): array
    {
        $optionalKeys = [
            'duration', 'expiration_date', 'rating', 'view_count', 'publication_date',
            'family_friendly', 'restriction', 'gallery_loc', 'price', 'requires_subscription',
            'uploader', 'live',
        ];
        $options = [];
        foreach ($optionalKeys as $key) {
            if (array_key_exists($key, $entry) && $entry[$key] !== null && $entry[$key] !== '') {
                $options[$key] = $entry[$key];
            }
        }

        return $options;
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
