<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class SitemapGeneratorPage extends Page
{
    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationGroup(): string
    {
        return __('filament-sitemap-generator::navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-sitemap-generator::navigation.label');
    }

    public static function getTitle(): string
    {
        return __('filament-sitemap-generator::page.title');
    }

    protected string $view = 'filament-panels::pages.page';

    protected static ?string $slug = 'sitemap-generator';

    protected ?SitemapGeneratorService $sitemapGenerator = null;

    public function mount(SitemapGeneratorService $sitemapGenerator): void
    {
        $this->sitemapGenerator = $sitemapGenerator;
    }

    protected function getSitemapGenerator(): SitemapGeneratorService
    {
        return $this->sitemapGenerator ?? app(SitemapGeneratorService::class);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('filament-sitemap-generator::page.generate_button'))
                ->action(function (): void {
                    $this->runGeneration();
                }),
        ];
    }

    private function runGeneration(): void
    {
        try {
            $this->getSitemapGenerator()->generate();
            Notification::make()
                ->title(__('filament-sitemap-generator::page.notification_success'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament-sitemap-generator::page.notification_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
