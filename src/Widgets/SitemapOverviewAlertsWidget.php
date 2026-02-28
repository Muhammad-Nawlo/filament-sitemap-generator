<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;
use MuhammadNawlo\FilamentSitemapGenerator\Pages\SitemapSettingsPage;

class SitemapOverviewAlertsWidget extends Widget
{
    protected  string $view = 'filament-sitemap-generator::widgets.sitemap-overview-alerts';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public function getViewData(): array
    {
        $settingsTableExists = Schema::hasTable('sitemap_settings');
        $hasStaticUrls = false;
        $hasModels = false;
        $autoGenerationEnabled = false;

        if ($settingsTableExists) {
            try {
                $settings = SitemapSetting::getSettings();
                $staticUrls = $settings->static_urls;
                $models = $settings->models;
                $hasStaticUrls = is_array($staticUrls) && count(array_filter($staticUrls, fn ($u) => ! empty($u['url'] ?? ''))) > 0;
                $hasModels = is_array($models) && count(array_filter($models, fn ($m) => ! empty($m['model_class'] ?? '') && ! empty($m['enabled'] ?? true))) > 0;
                $autoGenerationEnabled = $settings->isAutoGenerationEnabled();
            } catch (\Throwable) {
            }
        } else {
            $config = config('filament-sitemap-generator', []);
            $hasStaticUrls = ! empty($config['static_urls']);
            $hasModels = ! empty($config['models']);
            $autoGenerationEnabled = (bool) ($config['schedule']['enabled'] ?? false);
        }

        return [
            'settingsUrl' => SitemapSettingsPage::getUrl(),
            'showNoStaticUrlsWarning' => ! $hasStaticUrls,
            'showNoModelsWarning' => ! $hasModels,
            'autoGenerationEnabled' => $autoGenerationEnabled,
        ];
    }
}
