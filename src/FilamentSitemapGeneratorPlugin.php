<?php

namespace MuhammadNawlo\FilamentSitemapGenerator;

use Filament\Contracts\Plugin;
use Filament\Panel;
use MuhammadNawlo\FilamentSitemapGenerator\Pages\SitemapGeneratorPage;

class FilamentSitemapGeneratorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-sitemap-generator';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SitemapGeneratorPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
