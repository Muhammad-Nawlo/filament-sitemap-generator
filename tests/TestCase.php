<?php

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\LivewireServiceProvider;
use MuhammadNawlo\FilamentSitemapGenerator\FilamentSitemapGeneratorServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Sitemap\SitemapServiceProvider;

class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            if (str_starts_with($modelName, 'MuhammadNawlo\\FilamentSitemapGenerator\\Tests\\Fixtures\\Models\\')) {
                return 'MuhammadNawlo\\FilamentSitemapGenerator\\Tests\\Fixtures\\Factories\\' . class_basename($modelName) . 'Factory';
            }

            return 'MuhammadNawlo\\FilamentSitemapGenerator\\Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentSitemapGeneratorServiceProvider::class,
            SitemapServiceProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0755, true);
        }
        $app['config']->set('filament-sitemap-generator.path', $testingPath . DIRECTORY_SEPARATOR . 'sitemap.xml');
        $app['config']->set('filament-sitemap-generator.schedule.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function tearDown(): void
    {
        $this->cleanSitemapFiles();
        parent::tearDown();
    }

    private function cleanSitemapFiles(): void
    {
        $path = config('filament-sitemap-generator.path');
        if (! is_string($path)) {
            return;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            return;
        }
        $files = array_merge(
            glob($dir . DIRECTORY_SEPARATOR . 'sitemap.xml') ?: [],
            glob($dir . DIRECTORY_SEPARATOR . 'sitemap-*.xml') ?: []
        );
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
