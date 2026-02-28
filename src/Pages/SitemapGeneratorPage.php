<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapOverviewAlertsWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapPreviewWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapRecentRunsWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapRunsTableWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapStatsWidget;

class SitemapGeneratorPage extends Page
{
    public ?string $activeTab = 'overview';

    public bool $isGenerating = false;

    public static function getNavigationGroup(): string
    {
        return __('filament-sitemap-generator::navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-sitemap-generator::navigation.label');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-map';
    }

    public function getTitle(): string
    {
        return __('filament-sitemap-generator::page.title');
    }

    protected static ?string $slug = 'sitemap-generator';

    protected ?SitemapGeneratorService $sitemapGenerator = null;

    public function mount(SitemapGeneratorService $sitemapGenerator): void
    {
        $this->sitemapGenerator = $sitemapGenerator;
        if (blank($this->activeTab)) {
            $this->activeTab = 'overview';
        }
    }

    protected function getSitemapGenerator(): SitemapGeneratorService
    {
        return $this->sitemapGenerator ?? app(SitemapGeneratorService::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->livewireProperty('activeTab')
                ->contained(false)
                ->tabs([
                    'overview' => Tab::make(__('filament-sitemap-generator::page.tab_overview'))
                        ->schema([
                            Livewire::make(SitemapOverviewAlertsWidget::class)->key('sitemap-overview-alerts'),
                            Grid::make(1)
                                ->schema([
                                    Livewire::make(SitemapStatsWidget::class)->key('sitemap-stats'),
                                    Livewire::make(SitemapRecentRunsWidget::class)->key('sitemap-recent-runs'),
                                ]),
                        ]),
                    'preview' => Tab::make(__('filament-sitemap-generator::page.tab_preview'))
                        ->schema([
                            Livewire::make(SitemapPreviewWidget::class)->key('sitemap-preview'),
                        ]),
                    'runs' => Tab::make(__('filament-sitemap-generator::page.tab_runs'))
                        ->schema([
                            Livewire::make(SitemapRunsTableWidget::class)->key('sitemap-runs-table'),
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $service = $this->getSitemapGenerator();
        $path = $service->getSitemapPath();
        $publicPath = rtrim(public_path(), \DIRECTORY_SEPARATOR . '/');
        $relativePath = trim(str_replace($publicPath, '', $path), \DIRECTORY_SEPARATOR . '/');
        $sitemapUrl = asset(str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath));

        return [
            ActionGroup::make([
                Action::make('generate')
                    ->label(__('filament-sitemap-generator::page.action_generate'))
                    ->action(fn () => $this->runGeneration())
                    ->disabled(fn () => $this->isGenerating),
                Action::make('regenerate')
                    ->label(__('filament-sitemap-generator::page.action_regenerate'))
                    ->action(function () use ($service) {
                        $service->clear();
                        $this->runGeneration();
                    })
                    ->disabled(fn (): bool => $this->isGenerating),
                Action::make('clear')
                    ->label(__('filament-sitemap-generator::page.action_clear'))
                    ->modalHeading(__('filament-sitemap-generator::page.confirm_clear_title'))
                    ->modalDescription(__('filament-sitemap-generator::page.confirm_clear_body'))
                    ->modalSubmitActionLabel('Clear')
                    ->color('warning')
                    ->action(function (): void {
                        $removed = $this->getSitemapGenerator()->clear();
                        Notification::make()
                            ->title($removed ? __('filament-sitemap-generator::page.notification_cleared') : __('filament-sitemap-generator::page.notification_cleared_empty'))
                            ->success()
                            ->send();
                    })
                    ->disabled(fn (): bool => $this->isGenerating),
                Action::make('download')
                    ->label(__('filament-sitemap-generator::page.action_download'))
                    ->url($sitemapUrl)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => file_exists($path)),
                Action::make('validate')
                    ->label(__('filament-sitemap-generator::page.action_validate'))
                    ->action(fn () => $this->runValidation())
                    ->disabled(fn (): bool => $this->isGenerating),
                Action::make('preview')
                    ->label(__('filament-sitemap-generator::page.action_preview'))
                    ->url($sitemapUrl)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => file_exists($path)),
            ])
                ->label(__('filament-sitemap-generator::page.generate_button'))
                ->icon('heroicon-o-cog-6-tooth'),
        ];
    }

    private function runGeneration(): void
    {
        $this->isGenerating = true;

        try {
            $run = $this->getSitemapGenerator()->generate();

            if ($run->isSuccess()) {
                Notification::make()
                    ->title(__('filament-sitemap-generator::page.notification_success'))
                    ->success()
                    ->send();

                if ($run->total_urls > 50000) {
                    Notification::make()
                        ->title(__('filament-sitemap-generator::page.notification_warning_many_urls'))
                        ->warning()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title(__('filament-sitemap-generator::page.notification_failed'))
                    ->body($run->error_message ?? '')
                    ->danger()
                    ->send();
            }
        } finally {
            $this->isGenerating = false;
        }
    }

    private function runValidation(): void
    {
        $result = $this->getSitemapGenerator()->validateSitemap();

        if ($result['status'] === 'missing') {
            Notification::make()
                ->title(__('filament-sitemap-generator::page.validation_missing_label'))
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        if ($result['status'] === 'invalid') {
            Notification::make()
                ->title(__('filament-sitemap-generator::page.validation_invalid_label'))
                ->body($result['message'] ?? __('filament-sitemap-generator::page.validation_invalid'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('filament-sitemap-generator::page.validation_valid'))
            ->success()
            ->send();
    }
}
