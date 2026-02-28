<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun;

class SitemapStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $run = SitemapRun::query()->latestSuccessful()->first();

        if ($run === null) {
            return [
                Stat::make(__('filament-sitemap-generator::page.stats_last_generated'), '-')
                    ->description(__('filament-sitemap-generator::page.stats_no_runs')),
            ];
        }

        $fileSizeKb = $run->file_size > 0 ? (int) round($run->file_size / 1024) : 0;

        return [
            Stat::make(__('filament-sitemap-generator::page.stats_last_generated'), $run->generated_at->format('M j, Y H:i'))
                ->description(__('filament-sitemap-generator::page.stats_status_' . $run->status)),
            Stat::make(__('filament-sitemap-generator::page.stats_total_urls'), number_format($run->total_urls)),
            Stat::make(__('filament-sitemap-generator::page.stats_static_urls'), number_format($run->static_urls)),
            Stat::make(__('filament-sitemap-generator::page.stats_model_urls'), number_format($run->model_urls)),
            Stat::make(__('filament-sitemap-generator::page.stats_file_size'), $fileSizeKb . ' KB'),
            Stat::make(__('filament-sitemap-generator::page.stats_duration'), $run->duration_ms . ' ms'),
            Stat::make(__('filament-sitemap-generator::page.stats_status'), $run->status)
                ->color($run->isSuccess() ? 'success' : 'danger'),
        ];
    }
}
