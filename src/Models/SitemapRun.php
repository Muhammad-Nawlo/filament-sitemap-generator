<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Models;

use Illuminate\Database\Eloquent\Model;

class SitemapRun extends Model
{
    public function getTable(): string
    {
        return config('filament-sitemap-generator.sitemap_runs_table', 'sitemap_runs');
    }

    protected $fillable = [
        'generated_at',
        'total_urls',
        'static_urls',
        'model_urls',
        'file_size',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'total_urls' => 'integer',
            'static_urls' => 'integer',
            'model_urls' => 'integer',
            'file_size' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function scopeLatestSuccessful($query)
    {
        return $query->where('status', 'success')->orderByDesc('generated_at');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public static function getAverageDuration(): ?float
    {
        $avg = static::query()
            ->where('status', 'success')
            ->where('duration_ms', '>', 0)
            ->avg('duration_ms');

        return $avg !== null ? (float) $avg : null;
    }

    public static function getSlowestRun(): ?static
    {
        return static::query()
            ->where('status', 'success')
            ->orderByDesc('duration_ms')
            ->first();
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }
}
