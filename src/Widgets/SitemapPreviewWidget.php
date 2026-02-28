<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Widgets;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;

class SitemapPreviewWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament-sitemap-generator::widgets.sitemap-preview';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getPreviewData(): array
    {
        return app(SitemapGeneratorService::class)->getPreviewData();
    }

    public function getSitemapUrl(): string
    {
        $path = app(SitemapGeneratorService::class)->getSitemapPath();
        $publicPath = rtrim(public_path(), \DIRECTORY_SEPARATOR . '/');
        $relativePath = trim(str_replace($publicPath, '', $path), \DIRECTORY_SEPARATOR . '/');

        return asset(str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath));
    }

    public function runValidate(): void
    {
        $result = app(SitemapGeneratorService::class)->validateSitemap();
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
                ->body($result['message'] ?? '')
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
