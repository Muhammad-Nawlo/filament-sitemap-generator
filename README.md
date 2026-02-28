# muhammad-nawlo/filament-sitemap-generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/muhammad-nawlo/filament-sitemap-generator.svg?style=flat-square)](https://packagist.org/packages/muhammad-nawlo/filament-sitemap-generator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/muhammad-nawlo/filament-sitemap-generator/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/muhammad-nawlo/filament-sitemap-generator/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/muhammad-nawlo/filament-sitemap-generator/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/muhammad-nawlo/filament-sitemap-generator/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/muhammad-nawlo/filament-sitemap-generator.svg?style=flat-square)](https://packagist.org/packages/muhammad-nawlo/filament-sitemap-generator)

A config-driven Filament plugin for Laravel that generates XML sitemaps with optional splitting, news, images, alternates, and search-engine ping. Built on [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap).

**Compatibility:** Filament v3.2+, v4.x, and v5.x. The Filament page uses getter overrides only for navigation group, label, and title (no static property redeclaration), so it stays compatible with differing parent types across Filament versions.

## Installation

```bash
composer require muhammad-nawlo/filament-sitemap-generator
```

Publish the config file:

```bash
php artisan vendor:publish --tag="filament-sitemap-generator-config"
```

Configure `config/filament-sitemap-generator.php` (path, static URLs, models, schedule, queue, news, ping) as needed.

## Usage

- **Filament:** Open the **Sitemap** page (under Settings) and click **Generate Sitemap**.
- **CLI:** `php artisan filament-sitemap-generator:generate` (runs synchronously or dispatches a job if queue is enabled).
- **Scheduler:** Enable `schedule.enabled` in config; the command is registered at your chosen frequency (e.g. daily).

---

## Feature Matrix

| Feature | Status | Notes |
|---------|--------|-------|
| Manual generation via Filament | ✅ Implemented | Single page under Settings; one "Generate Sitemap" action |
| CLI generation | ✅ Implemented | `php artisan filament-sitemap-generator:generate` |
| Queue support | ✅ Implemented | Optional; config-driven connection and queue name |
| Scheduler support | ✅ Implemented | Optional; config-driven frequency (e.g. daily, hourly) |
| Config-driven static URLs | ✅ Implemented | `static_urls` array with url, priority, changefreq, lastmod |
| Model-based URLs | ✅ Implemented | `models` config; route or `getSitemapUrl()` per model |
| Chunked model processing | ✅ Implemented | Configurable `chunk_size`; never loads full table |
| 50,000 URL file splitting | ✅ Implemented | Configurable `max_urls_per_file`; flush when limit reached |
| Sitemap index generation | ✅ Implemented | When multiple parts exist, main path becomes index |
| lastmod support | ✅ Implemented | Static: config key; models: `getSitemapLastModified()` or `updated_at` |
| changefreq support | ✅ Implemented | Per static entry and per model config |
| priority support | ✅ Implemented | Per static entry and per model config |
| Alternate URLs (hreflang) | ✅ Implemented | Model method `getAlternateUrls()`; locale => url |
| Image sitemap support | ✅ Implemented | Model method `getSitemapImages()`; url + caption |
| Google News sitemap | ✅ Implemented | Separate `sitemap-news.xml`; 48-hour window; config-driven |
| Search engine ping | ✅ Implemented | Google and Bing; main sitemap URL only; try/catch per engine |
| Multi-site support | 🚧 Planned | Single site only; no tenant or domain-specific sitemaps |
| Storage disk abstraction | ❌ Not supported | Writes to filesystem path only; no Laravel disk abstraction |
| Event hooks | ❌ Not supported | No before/after or URL-collected events; extension via config/service binding only |

---

## Performance Characteristics

- **Chunk-based model iteration:** Models are read via `Model::query()->chunk($chunkSize, callback)`. Only one chunk of records is in memory at a time. This avoids loading entire tables and keeps memory usage bounded by chunk size and the size of the in-memory sitemap (see below).

- **Memory usage:** Peak memory is dominated by (1) one Spatie `Sitemap` instance holding up to `max_urls_per_file` URL tags (default 50,000), and (2) one chunk of Eloquent models (default 500 records). No full-table or full-sitemap accumulation in memory.

- **Max URLs per sitemap file:** When the number of URLs added reaches `max_urls_per_file` (default 50,000), the current sitemap is written to `sitemap-{n}.xml` and a new in-memory sitemap is started. No single file exceeds this limit.

- **Index generation:** If any part file is written, the main path (`sitemap.xml`) is written as a sitemap index that references all part URLs. If the total URL count never reaches the limit, a single sitemap is written to the main path and no index is produced.

- **Recommended queue usage for large sites:** For sites with many thousands of URLs, run generation via the CLI with `queue.enabled` set to `true`, or trigger the command from the scheduler. Avoid running "Generate Sitemap" from the Filament page for large sites, as it runs in the web request and can hit time or memory limits.

- **Recommended chunk_size tuning:** Default is 500. Use a smaller value (e.g. 250) if model instances are large or memory is constrained; use a larger value (e.g. 1000) to reduce query round-trips when models are small and memory is sufficient.

---

## Testing Strategy

The package uses **Pest** for tests and **Orchestra Testbench** for Laravel application bootstrapping in a package context.

**What is tested (or should be covered by contributors):**

- **Command execution:** The `filament-sitemap-generator:generate` command runs and, when queue is disabled, invokes the service and returns the correct exit code; when queue is enabled, it dispatches the job and outputs the expected message.
- **Job dispatch:** With queue enabled, the command dispatches `GenerateSitemapJob` with optional connection/queue from config; the job can be asserted as queued or run synchronously in tests.
- **Service generation:** `SitemapGeneratorService::generate()` reads config, writes sitemap file(s) to the configured path, and optionally builds an index and pings search engines without failing on ping errors.
- **Splitting behavior:** When URL count exceeds `max_urls_per_file`, multiple part files and an index are produced; when under the limit, a single sitemap file is written to the main path.
- **News sitemap logic:** With news enabled, `sitemap-news.xml` is written in the same directory; only records with publication date within the last 48 hours are included.

**How to run tests:**

```bash
composer test
```

This runs the Pest test suite (typically `./vendor/bin/pest`).

**Contributors:** Add tests in `tests/` using Pest syntax. Use the base `TestCase` (which extends Orchestra Testbench’s package test case) so the Laravel application and package service provider are loaded. Prefer feature tests that run the command or service and assert on file output and exit codes; add unit tests for service methods where it helps guard against regressions.

---

## Compatibility Table

| Laravel | Filament | PHP | Status |
|---------|----------|-----|--------|
| 10.x | 3.2+ / 4.x / 5.x | 8.2+ | Supported (via composer constraints) |
| 11.x | 3.2+ / 4.x / 5.x | 8.2+ | Supported (via composer constraints) |

Composer constraints: `php: ^8.2`, `filament/filament: ^3.2 || ^4.0 || ^5.0`. Laravel version is implied by Filament and other dependencies. CI may run on a subset of these; report issues for specific version combinations.

---

## Architecture Diagram

```
┌─────────────────────┐
│   Filament Page     │  (Settings → Sitemap → "Generate Sitemap")
│  SitemapGenerator   │
└──────────┬──────────┘
           │ calls generate()
           ▼
┌─────────────────────┐
│      Command        │  filament-sitemap-generator:generate
│ GenerateSitemapCmd  │  (sync) or dispatch job (queue)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│     Queue Job       │  GenerateSitemapJob (if queue.enabled)
│ (optional)          │  handle() → service->generate()
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ SitemapGenerator    │  buildStandardSitemaps → buildIndex (if needed)
│     Service         │  → buildNewsSitemap (if enabled) → pingSearchEngines (if enabled)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Spatie Sitemap      │  Sitemap, SitemapIndex, Tags\Url (news, image, alternate)
│     Builder         │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│   XML Files         │  sitemap.xml (single or index) + sitemap-1.xml, sitemap-2.xml, …
│ (single or index    │  Optional: sitemap-news.xml
│  + parts)           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Optional Search     │  GET Google/Bing ping URLs (main sitemap URL); try/catch per engine
│ Engine Ping         │
└─────────────────────┘
```

---

## Extension Points

### Optional model methods

Implement these on Eloquent models referenced in `config('filament-sitemap-generator.models')` or `config('filament-sitemap-generator.news.models')` to customize URL, lastmod, alternates, images, or news metadata. All methods are optional; fallbacks use config or standard attributes.

| Method | Return type | Description |
|--------|-------------|-------------|
| `getSitemapUrl()` | `string` | Canonical URL for this record. If absent, URL is built from `models.*.route` and the model for `route()`. |
| `getSitemapLastModified()` | `\DateTimeInterface` | Last modification date for `<lastmod>`. If absent, `updated_at` is used when present. |
| `getAlternateUrls()` | `array<string, string>` | Map of locale code => absolute URL for hreflang alternates (e.g. `['en' => 'https://...', 'fr' => 'https://...']`). |
| `getSitemapImages()` | `array<int, array{url: string, caption?: string}>` | List of image entries; each must have `url`; `caption` is optional. URLs are normalized with the configured base URL. |
| `getSitemapNewsTitle()` | `string` | Title for Google News `<title>`. If absent, `title` or `name` attribute is used. Only relevant when the model is in `news.models`. |
| `getSitemapNewsPublicationDate()` | `\DateTimeInterface` | Publication date for Google News. If absent, `published_at`, `updated_at`, or `created_at` is used. Only relevant when the model is in `news.models`. |

### Extending via config

- **Custom model configuration:** Add entries to `config('filament-sitemap-generator.models')` with model class as key and `priority`, `changefreq`, and `route` (required if the model does not implement `getSitemapUrl()`). Use `route` to specify the named route used to build the URL (e.g. `'posts.show'`).
- **Override base URL:** Set `config('filament-sitemap-generator.base_url')` to a full base URL (e.g. `https://example.com`). All non-absolute URLs (static and model-generated) are prefixed with this value. If `null`, `config('app.url')` is used.

### Custom service implementation

The service is bound as a singleton in the package service provider:

```php
$this->app->singleton(SitemapGeneratorService::class);
```

To use a custom implementation (e.g. to add URLs or change behavior), register your class in a service provider that runs after the package:

```php
$this->app->singleton(SitemapGeneratorService::class, MyCustomSitemapGeneratorService::class);
```

Ensure your implementation is compatible with callers that type-hint `SitemapGeneratorService` (Filament page, command, job) or provide the same public `generate(): bool` contract.

---

## Example Large-Site Configuration

Example production-style config for a site with 500,000+ URLs: queue and scheduler enabled, chunk size tuned, file splitting at 50,000 URLs, and ping enabled.

```php
// config/filament-sitemap-generator.php (excerpt for large-site scenario)

return [
    'path' => public_path('sitemap.xml'),
    'chunk_size' => 500,
    'max_urls_per_file' => 50000,
    'base_url' => null,

    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
        // ... other static entries
    ],
    'models' => [
        App\Models\Post::class => [
            'priority' => 0.8,
            'changefreq' => 'weekly',
            'route' => 'posts.show',
        ],
        App\Models\Category::class => [
            'priority' => 0.7,
            'changefreq' => 'weekly',
            'route' => 'categories.show',
        ],
        // ... other models
    ],

    'schedule' => [
        'enabled' => true,
        'frequency' => 'daily',
    ],
    'queue' => [
        'enabled' => true,
        'connection' => null,   // default
        'queue' => 'sitemaps', // dedicated queue recommended for large runs
    ],
    'news' => [
        'enabled' => true,
        'publication_name' => 'Your Site Name',
        'publication_language' => 'en',
        'models' => [App\Models\Post::class],
    ],
    'ping_search_engines' => [
        'enabled' => true,
        'engines' => ['google', 'bing'],
    ],
];
```

With this setup, `php artisan filament-sitemap-generator:generate` (or the daily schedule) dispatches the job to the `sitemaps` queue. A worker processes it; the service produces `sitemap-1.xml` through `sitemap-N.xml` (each ≤ 50,000 URLs), `sitemap.xml` as the index, and optionally `sitemap-news.xml`. Google and Bing are then pinged with the main sitemap URL. Ensure a queue worker is running (e.g. `php artisan queue:work --queue=sitemaps` or your production worker config).

---

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Muhammad-Nawlo](https://github.com/Muhammad-Nawlo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

# Architectural Overview

Structured technical overview of the package for maintainers and contributors.

---

## 1. High-Level Purpose

### 1.1 Problem Solved

The package provides **config-driven, Filament-backed XML sitemap generation** for Laravel applications. It allows site owners or automation to produce sitemap(s) that comply with common limits (e.g. 50,000 URLs per file), support news/image/alternate hints, and optionally ping search engines—without writing custom generation code.

### 1.2 Filament Integration

- **Plugin:** `FilamentSitemapGeneratorPlugin` implements `Filament\Contracts\Plugin`, registers a single custom page, and is attached to the **default panel only** inside `Filament::serving()` (and only if not already registered).
- **UI:** One Filament Page under the "Settings" group exposes a "Generate Sitemap" header action that invokes the service and shows success/failure via `Notification`. No generation logic lives in the page.
- **Coupling:** The package assumes a default Filament panel; it does not support selecting the panel or multi-panel registration.

### 1.3 Relationship to spatie/laravel-sitemap

- **Builds on:** Spatie’s `Sitemap`, `SitemapIndex`, and `Tags\Url` (with `addNews`, `addImage`, `addAlternate`) for XML structure and writing.
- **Adds:** Config-driven sources (static URLs + model lists), chunked model iteration, file splitting and index generation, news sitemap, ping, and Filament/CLI/queue/scheduler entry points. Spatie is used as the low-level sitemap builder, not as a drop-in replacement.

---

## 2. Architecture Breakdown

### 2.1 Service Provider

**Class:** `FilamentSitemapGeneratorServiceProvider` (extends `Spatie\LaravelPackageTools\PackageServiceProvider`)

**Responsibilities:**

- **Package configuration:** Name, config file, commands, install command (config publish, migrations prompt, GitHub star), optional migrations/translations/views based on existing paths.
- **Registration:** Binds `SitemapGeneratorService` as a **singleton** in `packageRegistered()`.
- **Boot:** In `packageBooted()`: registers the Filament plugin on the default panel (inside `Filament::serving()`), registers assets/icons (currently empty), publishes stubs when `stubs/` exists, registers the schedule when enabled, and adds a testing mixin.

**Notable details:** Schedule registration uses `$this->app->booted()` and resolves `Schedule` from the container; frequency is applied via `method_exists($event, $frequency)`. The stubs publish loop will error if `stubs/` is missing. Install command still references migrations even though the package does not appear to ship sitemap DB tables.

### 2.2 Plugin

**Class:** `FilamentSitemapGeneratorPlugin` (`Filament\Contracts\Plugin`)

**Responsibilities:**

- **Identity:** `getId()` returns `'filament-sitemap-generator'`.
- **Registration:** Registers `SitemapGenerator` as a Filament page in `register(Panel $panel)`; `boot()` is empty.
- **Factory:** `make()` and `get()` resolve the plugin from the container or current panel.

**Separation:** No business logic; only Filament registration.

### 2.3 Service Layer

**Class:** `SitemapGeneratorService`

**Responsibilities:**

- **Orchestration:** `generate()` reads config, then calls (in order) `buildStandardSitemaps()`, optionally `buildIndex()`, optionally `buildNewsSitemap()`, and optionally `pingSearchEngines()`.
- **Standard sitemaps:** `buildStandardSitemaps()` streams URLs (static + chunked models) into Spatie `Sitemap` instances, flushes to `sitemap-{n}.xml` when the count reaches `max_urls_per_file`, and either writes a single file to the main path or returns part URLs for the index.
- **Index:** `buildIndex()` builds a Spatie `SitemapIndex` from part URLs and writes it to the main path.
- **News:** `buildNewsSitemap()` builds a separate `sitemap-news.xml` from configured news models, filtering by publication date (last 48 hours) and using `getSitemapUrl` / `getSitemapNewsTitle` / `getSitemapNewsPublicationDate` (with fallbacks).
- **Ping:** `pingSearchEngines()` builds the main sitemap URL and GETs Google/Bing ping endpoints; failures are caught and do not affect generation.
- **URL/tag building:** `normalizeUrl()` / `getBaseUrl()`, `buildModelUrlTag()`, `resolveModelUrl()`, and helpers for lastmod, priority/changefreq, alternates, and images. All use `config('filament-sitemap-generator.*')` (and `config('app.url')` for base); the only other external dependency is optional `HttpClientFactory` for ping.

**Design:** Single public entry point (`generate()`), small private methods, no facades except config. Logic is centralized in the service; Filament, command, and job only call `generate()`.

### 2.4 Command and Job

**Command:** `GenerateSitemapCommand` (`filament-sitemap-generator:generate`)

- Resolves `SitemapGeneratorService` via constructor.
- If `queue.enabled` is true: dispatches `GenerateSitemapJob` (with optional connection/queue from config), prints "Sitemap generation dispatched.", returns 0.
- Otherwise: calls `$sitemapGenerator->generate()`, prints success or error, returns 0 or 1.

**Job:** `GenerateSitemapJob` (`ShouldQueue`)

- `handle(SitemapGeneratorService $sitemapGenerator)` only calls `$sitemapGenerator->generate()`. No generation logic in the job.

**Separation:** Command and job are thin adapters; all behavior is in the service.

### 2.5 Config Structure

| Key | Purpose |
|-----|--------|
| `path` | Main sitemap path (default `public_path('sitemap.xml')`) |
| `chunk_size` | Model query chunk size (default 500) |
| `max_urls_per_file` | Max URLs per file before splitting (default 50,000) |
| `base_url` | Override for absolute URLs (default `null` → `app.url`) |
| `static_urls` | List of `url`, `priority`, `changefreq`, optional `lastmod` |
| `models` | Map of model class => `priority`, `changefreq`, `route` (for URL when no `getSitemapUrl`) |
| `schedule.enabled` / `frequency` | Whether to schedule the command and with which frequency |
| `queue.enabled` / `connection` / `queue` | Whether to queue and which connection/queue |
| `news.enabled` / `publication_name` / `publication_language` / `models` | Google News sitemap |
| `ping_search_engines.enabled` / `engines` | Whether to ping and which engines (e.g. google, bing) |

### 2.6 Separation of Concerns

- **Strong:** Generation, splitting, index, news, and ping are all in the service; UI and CLI only invoke the service. Config is the single source for behavior.
- **Gaps:** Facade alias points at an empty `FilamentSitemapGenerator` class, not the service. Install command and migrations list suggest DB usage that the package does not implement. Stubs publish assumes a `stubs/` directory.

---

## 3. Execution Flow

### 3.1 Manual Generation (Filament)

1. User opens the Sitemap page and clicks "Generate Sitemap".
2. `SitemapGenerator::runGeneration()` runs in a try/catch, calls `SitemapGeneratorService::generate()`, then sends a success or danger notification with the exception message on failure.
3. No queue: generation runs in the current request. For large sitemaps this can hit time/memory limits.

### 3.2 CLI Generation

1. `php artisan filament-sitemap-generator:generate` runs `GenerateSitemapCommand::handle()`.
2. If `queue.enabled`: command dispatches `GenerateSitemapJob` and exits; the worker runs the job (see 3.3).
3. If not queued: command calls `SitemapGeneratorService::generate()` synchronously and returns exit code 0 or 1 with console output.

### 3.3 Queue Generation

1. Command dispatches `GenerateSitemapJob` (optionally with connection/queue from config).
2. Worker runs the job; container injects `SitemapGeneratorService` into `handle()`; job calls `generate()` once.
3. Same flow as synchronous generation, but in a worker process.

### 3.4 Scheduler Flow

1. When `schedule.enabled` is true, the provider registers in `app->booted()` a schedule entry for `filament-sitemap-generator:generate` with the configured frequency (e.g. `daily()`), using the container’s `Schedule` instance.
2. `schedule:run` (or cron) executes the command at that frequency; the command then behaves as in 3.2 (sync or queue depending on config).

### 3.5 File Splitting Logic

1. `buildStandardSitemaps()` keeps one in-memory `Sitemap` and a URL count.
2. Each static URL and each model URL (from chunked queries) is added via a closure that: if `count >= max_urls_per_file`, writes the current sitemap to `sitemap-{partNumber}.xml` in the same directory, appends its full URL to a list, creates a new `Sitemap`, resets count, then adds the new URL.
3. After all URLs: if no part file was ever written and `count > 0`, the single sitemap is written to the main path and an empty list is returned. If at least one part was written and the last chunk has URLs, that chunk is written as the next part and its URL is appended.
4. Part URLs use `getBaseUrl()` plus filename (e.g. `https://example.com/sitemap-1.xml`).

### 3.6 Sitemap Index Logic

1. If `buildStandardSitemaps()` returns a non-empty list of part URLs, `buildIndex()` is called.
2. A Spatie `SitemapIndex` is created, each part URL is added, and the index is written to the main path (overwriting the main `sitemap.xml`).
3. Result: main path is the index; part files are `sitemap-1.xml`, `sitemap-2.xml`, etc. If there is only one "part" (no split), the implementation still writes `sitemap-1.xml` and then the index, so the main file is always an index when splitting occurs; single-file case is when no flush ever happens, and the only sitemap is written directly to the main path.

---

## 4. Scalability Analysis

### 4.1 Memory Safety

- **Models:** Uses `Model::query()->chunk($chunkSize, callback)` so records are not all loaded at once; only the current chunk is in memory.
- **Standard sitemap:** One Spatie `Sitemap` is held in memory and written when the URL count hits `max_urls_per_file` or at the end. So at most ~50,000 `Url` tags in memory at once for the standard sitemap.
- **News:** Same chunked pattern; news sitemap is built in one `Sitemap` and written once; if news models are large, the 48-hour filter is applied per record in PHP (no DB-level date filter), so many old records can be loaded and then skipped.

### 4.2 Chunking Strategy

- Chunk size is configurable (default 500). Smaller chunks reduce peak memory and increase query round-trips; larger chunks do the opposite.
- Chunking is only for DB reads; the in-memory sitemap can still grow up to `max_urls_per_file` before flush.

### 4.3 Large Database Handling

- For standard sitemaps, chunking prevents loading the full table. For news, all chunks are iterated and filtered by date in PHP; for tables with many historical rows, a query-level date filter (e.g. `where('published_at', '>=', $cutoff)`) would be more scalable and is not implemented.

### 4.4 50,000 URL Compliance

- Flush is triggered when `count >= max_urls_per_file` (default 50,000), so no sitemap file exceeds that limit. Index file only references part URLs, so it stays small.

### 4.5 Performance Bottlenecks

- **Single request (Filament):** Long-running and memory-heavy for large sites; no progress feedback or timeout handling.
- **News:** No DB-level 48-hour filter; all records are streamed and filtered in PHP.
- **Ping:** Sequential GETs; failure of one engine does not block the others (try/catch per engine).
- **Base URL:** Built from config on every URL normalize; negligible cost.

---

## 5. SEO Capabilities

| Feature | Support | Notes |
|--------|--------|------|
| Standard sitemap | Yes | Static URLs + models; optional splitting + index |
| lastmod | Yes | Static: `lastmod` in config. Models: `getSitemapLastModified()` or `updated_at` |
| changefreq & priority | Yes | From config per static entry and per model config |
| Alternate URLs | Yes | Model method `getAlternateUrls()` → `[locale => url]`; applied via Spatie `addAlternate` |
| Google News | Yes | Separate `sitemap-news.xml`; 48-hour window; publication name/language from config; title/date from model methods or attributes |
| Image sitemap | Yes | Model method `getSitemapImages()` → list of `url`/`caption`; applied via Spatie `addImage` |
| Search engine ping | Yes | Google and Bing; main sitemap URL only (index or single file); failures caught |

**Model contracts (optional):** `getSitemapUrl()`, `getSitemapLastModified()`, `getAlternateUrls()`, `getSitemapImages()`, `getSitemapNewsTitle()`, `getSitemapNewsPublicationDate()`. Fallbacks use attributes like `title`, `updated_at`, `published_at`, etc.

---

## 6. Configuration Flexibility

### 6.1 Customizable

- Output path, chunk size, max URLs per file, base URL.
- Static URLs (with priority, changefreq, lastmod).
- Models and their options (priority, changefreq, route).
- Schedule on/off and frequency (any method on the schedule event, e.g. `daily`, `hourly`).
- Queue on/off, connection, and queue name.
- News on/off, publication name/language, list of models.
- Ping on/off and list of engine names (google, bing).

### 6.2 Not Customizable (Without Code Changes)

- News sitemap path (fixed as `sitemap-news.xml` in the same dir as main path).
- Part file naming (`sitemap-1.xml`, `sitemap-2.xml`, …).
- 48-hour window for news.
- Which engines are supported (only google/bing in the ping map).
- Filament panel (always default), page slug, navigation group/label.
- Schedule is only registered for the default app schedule (single environment).

### 6.3 Extensibility

- No events or hooks during generation; no way to add URLs or modify tags without forking or wrapping the service.
- No interface/contract for "sitemap source"; models are configured by class name and options only.
- Service is a concrete class; swapping implementation would require binding a different implementation in the container.

---

## 7. Strengths

- **Single responsibility:** Service owns all generation; Filament, command, and job are thin.
- **Config-driven:** One config file controls paths, limits, sources, schedule, queue, news, and ping.
- **Strict types and DI:** Service, command, and job use type hints and constructor injection; only config and optional HTTP factory are used as globals.
- **Spatie reuse:** Uses Spatie’s sitemap building and tags correctly (index, news, images, alternates).
- **Safe splitting:** Respects 50,000 URL limit and produces a valid index when multiple files are used.
- **Resilient ping:** Ping failures do not fail generation.
- **Chunked models:** Avoids loading full tables into memory for standard sitemaps.
- **Backward compatible:** Single-file behavior preserved when URL count stays under the limit.

---

## 8. Weaknesses and Limitations

- **Facade:** `FilamentSitemapGenerator` facade resolves to an empty class; it does not delegate to `SitemapGeneratorService`, so it is misleading and unused.
- **Stubs:** Provider calls `Filesystem::files(__DIR__ . '/../stubs/')` without checking the directory exists; will throw if `stubs/` is missing.
- **Install command:** References migrations and "askToRunMigrations" although the package has no real migration (only a stub name); can confuse installs.
- **News date filter:** 48-hour filter is done in PHP after loading chunks; no `where('date_column', '>=', $cutoff)` on the query, so large tables waste work and memory.
- **No events:** No "before/after generate" or "url collected" events for logging, caching, or third-party extensions.
- **Single panel:** Plugin is only registered on the default Filament panel; no multi-panel or explicit panel choice.
- **Filament sync:** Manual generation runs in the web request; large sitemaps can time out or exhaust memory with no guidance to use queue/CLI.
- **Ping scope:** Only the main sitemap URL is pinged; `sitemap-news.xml` is not pinged.
- **Schedule coupling:** Schedule registration assumes the app uses the default `Schedule` from the container; custom scheduler setups may not see the entry.

---

## 9. Suggested Improvements for Enterprise Readiness

1. **Wire facade:** Point `FilamentSitemapGenerator` at `SitemapGeneratorService` (or a small wrapper) so `FilamentSitemapGenerator::generate()` works and is documented.
2. **Guard stubs publish:** Check `is_dir(__DIR__ . '/../stubs/')` before iterating, or remove the publish if no stubs are shipped.
3. **Align install command:** Remove or implement migrations; if no DB is used, drop migration steps from the install command.
4. **News query filter:** Add an optional configurable date column (e.g. `published_at`) and apply `where($column, '>=', $cutoff)` in the news query so only recent rows are loaded.
5. **Events:** Dispatch events (e.g. `SitemapGenerating`, `SitemapGenerated`) with path and part count so apps can log, invalidate caches, or extend.
6. **Filament queue hint:** When queue is disabled and the page is used, consider a warning or info notification suggesting queue/CLI for large sitemaps.
7. **Ping news sitemap:** Optionally ping the news sitemap URL when news is enabled (or document that only the main index is pinged).
8. **Documentation:** Document model contracts (method names and return shapes), config options, and recommended chunk_size / queue usage for large sites.
9. **Testing:** Add unit tests for the service (e.g. splitting at 50k, index contents, news 48h filter) and integration tests for command and job.
10. **Optional interfaces:** Define optional interfaces (e.g. `SitemapUrlProvider`) for models so IDEs and static analysis can rely on a clear contract alongside the current convention-based methods.
