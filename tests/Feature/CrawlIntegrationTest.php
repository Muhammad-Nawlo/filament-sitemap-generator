<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Crawl\AllowAllShouldCrawl;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Crawl\ModifyPriorityHasCrawled;
use Spatie\Sitemap\Crawler\Profile as SitemapCrawlProfile;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapGenerator;
use Spatie\Sitemap\Tags\Url;

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.news.enabled', false);
    config()->set('filament-sitemap-generator.ping_search_engines.enabled', false);
    config()->set('filament-sitemap-generator.static_urls', []);
    config()->set('filament-sitemap-generator.models', []);
    config()->set('filament-sitemap-generator.crawl.enabled', false);
    config()->set('filament-sitemap-generator.crawl.url', null);
    syncSitemapSettingsToTestingDisk();
});

it('does not crawl when crawl is disabled', function (): void {
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->crawled_urls)->toBe(0);
    expect($run->total_urls)->toBe(1);
    expect($run->static_urls)->toBe(1);
});

it('merges crawled URLs and deduplicates', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page-a'));
    $sitemapWithTags->add(Url::create('https://example.com/page-b'));
    $sitemapWithTags->add(Url::create('https://example.com/page-a')); // duplicate

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.max_count', 100);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->crawled_urls)->toBe(2);
    expect($run->total_urls)->toBe(2);

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('page-a');
    expect($content)->toContain('page-b');
    expect(substr_count($content, 'page-a'))->toBe(1);
});

it('applies exclude_patterns to crawled URLs', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/admin/secret'));
    $sitemapWithTags->add(Url::create('https://example.com/public-page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.max_count', 100);
    config()->set('filament-sitemap-generator.crawl.exclude_patterns', ['*admin*']);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->crawled_urls)->toBe(1);
    expect($run->total_urls)->toBe(1);

    $path = Storage::disk('sitemap_testing')->path('sitemap.xml');
    $content = (string) file_get_contents($path);
    expect($content)->toContain('public-page');
    expect($content)->not->toContain('admin/secret');
});

it('continues with static and model URLs when crawl fails', function (): void {
    $failingGenerator = new class extends SitemapGenerator {
        public function __construct()
        {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            throw new \RuntimeException('Site unreachable');
        }
    };

    $this->app->instance(SitemapGenerator::class, $failingGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(0);
    expect($run->static_urls)->toBe(1);
    expect($run->total_urls)->toBe(1);
});

it('restores sitemap.crawl_profile config after crawl with custom crawl_profile', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    $beforeProfile = config('sitemap.crawl_profile');
    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.crawl_profile', SitemapCrawlProfile::class);
    syncSitemapSettingsToTestingDisk();

    app(SitemapGeneratorService::class)->generate();

    $afterProfile = config('sitemap.crawl_profile');
    expect($afterProfile)->toBe($beforeProfile);
});

it('does not throw when crawl_profile is invalid class name', function (): void {
    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.crawl_profile', 'NonExistentProfileClass');
    config()->set('filament-sitemap-generator.static_urls', [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->static_urls)->toBe(1);
    expect($run->total_urls)->toBe(1);
});

it('applies maximum_depth and completes with mock', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.maximum_depth', 2);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});

it('accepts valid should_crawl invokable class', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.should_crawl', AllowAllShouldCrawl::class);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});

it('does not throw when should_crawl is invalid class', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.should_crawl', 'NonExistentShouldCrawlClass');
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});

it('accepts valid has_crawled invokable class', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.has_crawled', ModifyPriorityHasCrawled::class);
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});

it('does not throw when has_crawled is invalid class', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.has_crawled', 'NonExistentHasCrawledClass');
    syncSitemapSettingsToTestingDisk();

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});

it('does not override sitemap config when execute_javascript is false', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('sitemap.execute_javascript', false);
    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.execute_javascript', false);
    syncSitemapSettingsToTestingDisk();

    $before = config('sitemap.execute_javascript');
    app(SitemapGeneratorService::class)->generate();
    $after = config('sitemap.execute_javascript');

    expect($after)->toBe($before);
});

it('restores sitemap.execute_javascript and chrome path after crawl when execute_javascript is true', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('sitemap.execute_javascript', false);
    config()->set('sitemap.chrome_binary_path', null);
    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.execute_javascript', true);
    syncSitemapSettingsToTestingDisk();

    $beforeJs = config('sitemap.execute_javascript');
    $beforeChrome = config('sitemap.chrome_binary_path');
    app(SitemapGeneratorService::class)->generate();
    $afterJs = config('sitemap.execute_javascript');
    $afterChrome = config('sitemap.chrome_binary_path');

    expect($afterJs)->toBe($beforeJs);
    expect($afterChrome)->toBe($beforeChrome);
});

it('completes without exception when execute_javascript is true and Browsershot is not installed', function (): void {
    $sitemapWithTags = Sitemap::create();
    $sitemapWithTags->add(Url::create('https://example.com/page'));

    $fakeGenerator = new class($sitemapWithTags) extends SitemapGenerator {
        public function __construct(
            private readonly Sitemap $sitemap
        ) {
            $crawler = \Spatie\Crawler\Crawler::create();
            parent::__construct($crawler);
        }

        public function setUrl(string $urlToBeCrawled): static
        {
            return $this;
        }

        public function getSitemap(): Sitemap
        {
            return $this->sitemap;
        }
    };

    $this->app->instance(SitemapGenerator::class, $fakeGenerator);

    config()->set('filament-sitemap-generator.crawl.enabled', true);
    config()->set('filament-sitemap-generator.crawl.url', 'https://example.com');
    config()->set('filament-sitemap-generator.crawl.execute_javascript', true);
    syncSitemapSettingsToTestingDisk();

    if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
        $this->markTestSkipped('Browsershot is installed; cannot test fallback in this environment.');
    }

    $run = app(SitemapGeneratorService::class)->generate();

    expect($run->status)->toBe('success');
    expect($run->crawled_urls)->toBe(1);
});
