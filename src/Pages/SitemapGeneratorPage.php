<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class SitemapGeneratorPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Sitemap';

    protected static ?string $title = 'Sitemap Generator';

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
                ->label('Generate Sitemap')
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
                ->title('Sitemap generated successfully')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Sitemap generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
