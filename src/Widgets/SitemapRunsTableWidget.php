<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Widgets;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class SitemapRunsTableWidget extends BaseTableWidget
{
    protected static ?string $heading = null;

    protected int | string | array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return (string) __('filament-sitemap-generator::page.tab_runs');
    }

    protected function getTableQuery(): ?Builder
    {
        return SitemapRun::query()->orderByDesc('generated_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('filament-sitemap-generator::page.table_id'))
                    ->sortable()
                    ->numeric(),
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
                    ->numeric()
                    ->sortable(),
                TextColumn::make('static_urls')
                    ->label(__('filament-sitemap-generator::page.stats_static_urls'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('model_urls')
                    ->label(__('filament-sitemap-generator::page.stats_model_urls'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file_size')
                    ->label(__('filament-sitemap-generator::page.stats_file_size'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) round($state / 1024) . ' KB' : '0 KB')
                    ->sortable(),
                TextColumn::make('duration_ms')
                    ->label(__('filament-sitemap-generator::page.stats_duration'))
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' ms')
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->label(__('filament-sitemap-generator::page.stats_last_generated'))
                    ->dateTime()
                    ->since()
                    ->tooltip(fn ($record): string => $record->generated_at->format('M j, Y H:i:s'))
                    ->sortable(),
                TextColumn::make('error_message')
                    ->label(__('filament-sitemap-generator::page.run_error_message'))
                    ->limit(40)
                    ->placeholder(__('filament-sitemap-generator::page.placeholder_empty'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-sitemap-generator::page.stats_status'))
                    ->options([
                        'success' => __('filament-sitemap-generator::page.stats_status_success'),
                        'failed' => __('filament-sitemap-generator::page.stats_status_failed'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => isset($data['value']) && $data['value'] !== ''
                        ? $query->where('status', $data['value'])
                        : $query),
                Filter::make('generated_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('filament-sitemap-generator::page.stats_last_generated') . ' (' . __('filament-sitemap-generator::page.filter_from') . ')'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('filament-sitemap-generator::page.stats_last_generated') . ' (' . __('filament-sitemap-generator::page.filter_until') . ')'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['from'])) {
                            $query->whereDate('generated_at', '>=', $data['from']);
                        }
                        if (! empty($data['until'])) {
                            $query->whereDate('generated_at', '<=', $data['until']);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from'])) {
                            $indicators[] = Indicator::make(__('filament-sitemap-generator::page.filter_from') . ' ' . $data['from']);
                        }
                        if (! empty($data['until'])) {
                            $indicators[] = Indicator::make(__('filament-sitemap-generator::page.filter_until') . ' ' . $data['until']);
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('filament-sitemap-generator::page.runs_view_details'))
                        ->icon('heroicon-o-eye')
                        ->modalHeading(__('filament-sitemap-generator::page.run_details'))
                        ->modalContent(fn (SitemapRun $record): \Illuminate\Contracts\View\View => view('filament-sitemap-generator::run-details-modal', ['run' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('filament-sitemap-generator::page.modal_close')),
                    Action::make('retry')
                        ->label(__('filament-sitemap-generator::page.runs_retry'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->action(function (): void {
                            $run = app(SitemapGeneratorService::class)->generate();
                            if ($run->isSuccess()) {
                                Notification::make()->title(__('filament-sitemap-generator::page.notification_success'))->success()->send();
                            } else {
                                Notification::make()
                                    ->title(__('filament-sitemap-generator::page.notification_failed'))
                                    ->body($run->error_message)
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('delete')
                        ->label(__('filament-sitemap-generator::page.runs_delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (SitemapRun $record) => $record->delete()),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }
}
