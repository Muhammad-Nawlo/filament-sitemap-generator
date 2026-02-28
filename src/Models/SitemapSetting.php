<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SitemapSetting extends Model
{
    public const SINGLETON_ID = 1;

    public function getTable(): string
    {
        return config('filament-sitemap-generator.sitemap_settings_table', 'sitemap_settings');
    }

    protected $fillable = [
        'static_urls',
        'models',
        'default_change_frequency',
        'default_priority',
        'auto_generate_enabled',
        'auto_generate_frequency',
        'storage_path',
        'filename',
        'gzip_enabled',
        'chunk_size',
        'large_site_mode',
        'enable_index_sitemap',
        'output_mode',
        'file_path',
        'disk',
        'disk_path',
        'visibility',
        'crawl_enabled',
        'crawl_url',
        'concurrency',
        'max_count',
        'maximum_depth',
        'exclude_patterns',
        'crawl_profile',
        'should_crawl',
        'has_crawled',
        'execute_javascript',
        'chrome_binary_path',
        'node_binary_path',
    ];

    protected function casts(): array
    {
        return [
            'static_urls' => 'array',
            'models' => 'array',
            'default_priority' => 'decimal:2',
            'auto_generate_enabled' => 'boolean',
            'gzip_enabled' => 'boolean',
            'chunk_size' => 'integer',
            'large_site_mode' => 'boolean',
            'enable_index_sitemap' => 'boolean',
            'crawl_enabled' => 'boolean',
            'exclude_patterns' => 'array',
            'execute_javascript' => 'boolean',
        ];
    }

    /**
     * Get the single settings instance. Creates with defaults if none exists.
     */
    public static function getSettings(): self
    {
        $instance = static::query()->where('singleton', 1)->first();

        if ($instance !== null) {
            return $instance;
        }

        return static::query()->create(array_merge(static::getDefaultAttributes(), ['singleton' => 1]));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getDefaultAttributes(): array
    {
        $config = config('filament-sitemap-generator', []);

        $staticUrls = $config['static_urls'] ?? [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']];
        $models = $config['models'] ?? [];
        $modelsArray = [];
        foreach ($models as $modelClass => $opts) {
            $modelsArray[] = [
                'model_class' => $modelClass,
                'url_resolver_method' => $opts['url_resolver_method'] ?? 'getSitemapUrl',
                'changefreq' => $opts['changefreq'] ?? null,
                'priority' => $opts['priority'] ?? null,
                'enabled' => true,
            ];
        }

        return [
            'static_urls' => $staticUrls,
            'models' => $modelsArray,
            'default_change_frequency' => $config['default_change_frequency'] ?? 'weekly',
            'default_priority' => $config['default_priority'] ?? 0.8,
            'auto_generate_enabled' => $config['schedule']['enabled'] ?? false,
            'auto_generate_frequency' => $config['schedule']['frequency'] ?? 'daily',
            'storage_path' => 'public',
            'filename' => 'sitemap.xml',
            'gzip_enabled' => false,
            'chunk_size' => (int) ($config['chunk_size'] ?? 1000),
            'large_site_mode' => false,
            'enable_index_sitemap' => false,
        ];
    }

    public function isAutoGenerationEnabled(): bool
    {
        return $this->auto_generate_enabled;
    }

    /**
     * Full filesystem path for the sitemap file (used when output_mode is 'file' or legacy).
     */
    public function getStorageFullPath(): string
    {
        if ($this->output_mode === 'file' && $this->file_path !== null && $this->file_path !== '') {
            return $this->file_path;
        }

        $path = $this->storage_path ?? 'public';
        $filename = $this->filename ?? 'sitemap.xml';

        if ($path === 'public' || $path === '') {
            return public_path($filename);
        }

        try {
            $disk = Storage::disk($path);

            return $disk->path($filename);
        } catch (\Throwable) {
            return public_path($filename);
        }
    }

    /**
     * Enforce singleton: only one row (singleton = 1).
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ((int) ($model->getAttribute('singleton')) !== 1) {
                $model->setAttribute('singleton', 1);
            }
        });
    }
}
