<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun;

class SitemapRecentRunsWidget extends BaseTableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getTableHeading(): string
    {
        return (string) __('filament-sitemap-generator::page.recent_activity');
    }

    protected function getTableQuery(): ?Builder
    {
        return SitemapRun::query()
            ->orderByDesc('generated_at')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label(__('filament-sitemap-generator::page.stats_status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'success'
                        ? __('filament-sitemap-generator::page.stats_status_success')
                        : __('filament-sitemap-generator::page.stats_status_failed')),
                TextColumn::make('total_urls')
                    ->label(__('filament-sitemap-generator::page.stats_total_urls'))
                    ->numeric(),
                TextColumn::make('duration_ms')
                    ->label(__('filament-sitemap-generator::page.stats_duration'))
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' ms'),
                TextColumn::make('file_size')
                    ->label(__('filament-sitemap-generator::page.stats_file_size'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) round($state / 1024) . ' KB' : '0 KB'),
                TextColumn::make('generated_at')
                    ->label(__('filament-sitemap-generator::page.stats_last_generated'))
                    ->since()
                    ->tooltip(fn (SitemapRun $record): string => $record->generated_at->format('M j, Y H:i:s')),
            ])
            ->paginated(false);
    }
}
