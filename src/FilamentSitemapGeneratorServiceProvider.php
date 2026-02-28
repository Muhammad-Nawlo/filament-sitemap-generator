<?php

namespace MuhammadNawlo\FilamentSitemapGenerator;

use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use MuhammadNawlo\FilamentSitemapGenerator\Commands\GenerateSitemapCommand;
use MuhammadNawlo\FilamentSitemapGenerator\Services\SitemapGeneratorService;
use MuhammadNawlo\FilamentSitemapGenerator\Testing\TestsFilamentSitemapGenerator;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSitemapGeneratorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-sitemap-generator';

    public static string $viewNamespace = 'filament-sitemap-generator';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('muhammad-nawlo/filament-sitemap-generator');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SitemapGeneratorService::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register($this->getAssets(), $this->getAssetPackageName());
        FilamentAsset::registerScriptData($this->getScriptData(), $this->getAssetPackageName());
        FilamentIcon::register($this->getIcons());

        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-sitemap-generator/{$file->getFilename()}"),
                ], 'filament-sitemap-generator-stubs');
            }
        }
        $this->registerSchedule();

        Testable::mixin(new TestsFilamentSitemapGenerator);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'muhammad-nawlo/filament-sitemap-generator';
    }

    protected function getAssets(): array
    {
        return [];
    }

    protected function getCommands(): array
    {
        return [
            GenerateSitemapCommand::class,
        ];
    }

    protected function getIcons(): array
    {
        return [];
    }

    protected function getRoutes(): array
    {
        return [];
    }

    protected function getScriptData(): array
    {
        return [];
    }

    protected function getMigrations(): array
    {
        return [];
    }

    private function registerSchedule(): void
    {
        if (config('filament-sitemap-generator.schedule.enabled', false) !== true) {
            return;
        }

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $event = $schedule->command('filament-sitemap-generator:generate');
            $frequency = config('filament-sitemap-generator.schedule.frequency', 'daily');
            if (is_string($frequency) && method_exists($event, $frequency)) {
                $event->{$frequency}();
            } else {
                $event->daily();
            }
        });
    }
}
