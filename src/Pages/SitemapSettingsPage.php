<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;
use Throwable;

/**
 * @property-read \Filament\Schemas\Schema $form
 */
class SitemapSettingsPage extends Page
{
    use \Filament\Pages\Concerns\CanUseDatabaseTransactions;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'sitemap-settings';

    public static function getNavigationGroup(): string
    {
        return __('filament-sitemap-generator::navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-sitemap-generator::page.settings_title');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public function getTitle(): string
    {
        return __('filament-sitemap-generator::page.settings_title');
    }

    public function mount(): void
    {
        if ($this->settingsTableExists()) {
            $this->fillForm();
        } else {
            $this->form->fill($this->getDefaultData());
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(\Filament\Support\Enums\Alignment::Start)
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-sitemap-generator::page.settings_save'))
                ->submit('save'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $changefreqOptions = [
            'always' => __('filament-sitemap-generator::page.changefreq_always'),
            'hourly' => __('filament-sitemap-generator::page.changefreq_hourly'),
            'daily' => __('filament-sitemap-generator::page.changefreq_daily'),
            'weekly' => __('filament-sitemap-generator::page.changefreq_weekly'),
            'monthly' => __('filament-sitemap-generator::page.changefreq_monthly'),
            'yearly' => __('filament-sitemap-generator::page.changefreq_yearly'),
            'never' => __('filament-sitemap-generator::page.changefreq_never'),
        ];

        return $schema
            ->components([
                Section::make(__('filament-sitemap-generator::page.settings_static_urls'))
                    ->description(__('filament-sitemap-generator::page.settings_static_urls_help'))
                    ->schema([
                        Repeater::make('static_urls')
                            ->schema([
                                TextInput::make('url')
                                    ->label(__('filament-sitemap-generator::page.settings_url'))
                                    ->required()
                                    ->rules(['required', 'string',
                                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                            if ($value === '' || (! str_starts_with($value, '/') && ! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://'))) {
                                                $fail(__('filament-sitemap-generator::page.settings_url') . ' ' . __('filament-sitemap-generator::page.validation_url_must_be_path_or_url'));
                                            }
                                        },
                                    ]),
                                Select::make('changefreq')
                                    ->label(__('filament-sitemap-generator::page.settings_change_frequency'))
                                    ->options($changefreqOptions)
                                    ->default('weekly'),
                                TextInput::make('priority')
                                    ->label(__('filament-sitemap-generator::page.settings_priority'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->step(0.1)
                                    ->default(0.8),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->addActionLabel(__('filament-sitemap-generator::page.settings_static_urls'))
                            ->defaultItems(0),
                    ]),
                Section::make(__('filament-sitemap-generator::page.settings_model_registration'))
                    ->description(__('filament-sitemap-generator::page.settings_model_registration_help'))
                    ->schema([
                        Repeater::make('models')
                            ->schema([
                                TextInput::make('model_class')
                                    ->label(__('filament-sitemap-generator::page.settings_model_class'))
                                    ->required()
                                    ->placeholder('App\\Models\\Post')
                                    ->rules(['required',
                                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                            if (! is_string($value) || $value === '') {
                                                return;
                                            }
                                            if (! class_exists($value)) {
                                                $fail(__('filament-sitemap-generator::page.settings_model_class') . ' ' . __('filament-sitemap-generator::page.validation_class_must_exist'));
                                            }
                                        },
                                    ]),
                                TextInput::make('url_resolver_method')
                                    ->label(__('filament-sitemap-generator::page.settings_url_resolver_method'))
                                    ->helperText(__('filament-sitemap-generator::page.settings_url_resolver_method_help'))
                                    ->default('getSitemapUrl'),
                                Select::make('changefreq')
                                    ->label(__('filament-sitemap-generator::page.settings_change_frequency'))
                                    ->options($changefreqOptions)
                                    ->nullable(),
                                TextInput::make('priority')
                                    ->label(__('filament-sitemap-generator::page.settings_priority'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->step(0.1)
                                    ->nullable(),
                                Toggle::make('enabled')
                                    ->label(__('filament-sitemap-generator::page.settings_enabled'))
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->defaultItems(0),
                    ]),
                Section::make(__('filament-sitemap-generator::page.settings_general'))
                    ->schema([
                        Select::make('default_change_frequency')
                            ->label(__('filament-sitemap-generator::page.settings_default_change_frequency'))
                            ->options($changefreqOptions)
                            ->default('weekly'),
                        TextInput::make('default_priority')
                            ->label(__('filament-sitemap-generator::page.settings_default_priority'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.1)
                            ->default(0.8),
                        TextInput::make('storage_path')
                            ->label(__('filament-sitemap-generator::page.settings_storage_path'))
                            ->helperText(__('filament-sitemap-generator::page.settings_storage_path_help'))
                            ->default('public'),
                        TextInput::make('filename')
                            ->label(__('filament-sitemap-generator::page.settings_filename'))
                            ->helperText(__('filament-sitemap-generator::page.settings_filename_help'))
                            ->default('sitemap.xml'),
                        Toggle::make('gzip_enabled')
                            ->label(__('filament-sitemap-generator::page.settings_gzip_enabled'))
                            ->default(false),
                        Toggle::make('enable_index_sitemap')
                            ->label(__('filament-sitemap-generator::page.settings_enable_index_sitemap'))
                            ->default(false),
                    ])
                    ->columns(2),
                Section::make(__('filament-sitemap-generator::page.settings_output'))
                    ->schema([
                        Select::make('output_mode')
                            ->label(__('filament-sitemap-generator::page.settings_output_mode'))
                            ->options([
                                'file' => __('filament-sitemap-generator::page.settings_output_mode_file'),
                                'disk' => __('filament-sitemap-generator::page.settings_output_mode_disk'),
                            ])
                            ->default('file')
                            ->live(),
                        TextInput::make('file_path')
                            ->label(__('filament-sitemap-generator::page.settings_file_path'))
                            ->helperText(__('filament-sitemap-generator::page.settings_file_path_help'))
                            ->required(fn (Get $get): bool => $get('output_mode') === 'file')
                            ->visible(fn (Get $get): bool => $get('output_mode') === 'file'),
                        TextInput::make('disk')
                            ->label(__('filament-sitemap-generator::page.settings_disk'))
                            ->helperText(__('filament-sitemap-generator::page.settings_disk_help'))
                            ->required(fn (Get $get): bool => $get('output_mode') === 'disk')
                            ->visible(fn (Get $get): bool => $get('output_mode') === 'disk'),
                        TextInput::make('disk_path')
                            ->label(__('filament-sitemap-generator::page.settings_disk_path'))
                            ->helperText(__('filament-sitemap-generator::page.settings_disk_path_help'))
                            ->required(fn (Get $get): bool => $get('output_mode') === 'disk')
                            ->visible(fn (Get $get): bool => $get('output_mode') === 'disk'),
                        Select::make('visibility')
                            ->label(__('filament-sitemap-generator::page.settings_visibility'))
                            ->options([
                                'public' => __('filament-sitemap-generator::page.settings_visibility_public'),
                                'private' => __('filament-sitemap-generator::page.settings_visibility_private'),
                            ])
                            ->default('public')
                            ->visible(fn (Get $get): bool => $get('output_mode') === 'disk'),
                    ])
                    ->columns(2),
                Section::make(__('filament-sitemap-generator::page.settings_crawling'))
                    ->description(__('filament-sitemap-generator::page.settings_crawling_help'))
                    ->schema([
                        Toggle::make('crawl_enabled')
                            ->label(__('filament-sitemap-generator::page.settings_crawl_enabled'))
                            ->default(false)
                            ->live(),
                        TextInput::make('crawl_url')
                            ->label(__('filament-sitemap-generator::page.settings_crawl_url'))
                            ->helperText(__('filament-sitemap-generator::page.settings_crawl_url_help'))
                            ->url()
                            ->required(fn (Get $get): bool => (bool) $get('crawl_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                        TextInput::make('concurrency')
                            ->label(__('filament-sitemap-generator::page.settings_concurrency'))
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                        TextInput::make('max_count')
                            ->label(__('filament-sitemap-generator::page.settings_max_count'))
                            ->helperText(__('filament-sitemap-generator::page.settings_max_count_help'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                        TextInput::make('maximum_depth')
                            ->label(__('filament-sitemap-generator::page.settings_maximum_depth'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                        Textarea::make('exclude_patterns')
                            ->label(__('filament-sitemap-generator::page.settings_exclude_patterns'))
                            ->helperText(__('filament-sitemap-generator::page.settings_exclude_patterns_help'))
                            ->rows(3)
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (is_string($state) ? $state : ''))
                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? array_values(array_filter(array_map('trim', explode("\n", $state)))) : (is_array($state) ? $state : []))
                            ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                    ])
                    ->columns(2),
                Section::make(__('filament-sitemap-generator::page.settings_advanced_crawler'))
                    ->schema([
                        TextInput::make('crawl_profile')
                            ->label(__('filament-sitemap-generator::page.settings_crawl_profile'))
                            ->helperText(__('filament-sitemap-generator::page.settings_crawl_profile_help'))
                            ->placeholder('Spatie\Sitemap\Crawler\Profile')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }
                                    if (! is_string($value) || ! class_exists($value)) {
                                        $fail(__('filament-sitemap-generator::page.settings_crawl_profile') . ' ' . __('filament-sitemap-generator::page.validation_class_must_exist'));
                                    }
                                },
                            ]),
                        TextInput::make('should_crawl')
                            ->label(__('filament-sitemap-generator::page.settings_should_crawl'))
                            ->placeholder('App\Crawl\AllowAllShouldCrawl')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }
                                    if (! is_string($value) || ! class_exists($value)) {
                                        $fail(__('filament-sitemap-generator::page.settings_should_crawl') . ' ' . __('filament-sitemap-generator::page.validation_class_must_exist'));
                                    }
                                },
                            ]),
                        TextInput::make('has_crawled')
                            ->label(__('filament-sitemap-generator::page.settings_has_crawled'))
                            ->placeholder('App\Crawl\ModifyPriorityHasCrawled')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }
                                    if (! is_string($value) || ! class_exists($value)) {
                                        $fail(__('filament-sitemap-generator::page.settings_has_crawled') . ' ' . __('filament-sitemap-generator::page.validation_class_must_exist'));
                                    }
                                },
                            ]),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                Section::make(__('filament-sitemap-generator::page.settings_js_execution'))
                    ->description(__('filament-sitemap-generator::page.settings_js_execution_help'))
                    ->schema([
                        Toggle::make('execute_javascript')
                            ->label(__('filament-sitemap-generator::page.settings_execute_javascript'))
                            ->default(false)
                            ->live(),
                        TextInput::make('chrome_binary_path')
                            ->label(__('filament-sitemap-generator::page.settings_chrome_binary_path'))
                            ->visible(fn (Get $get): bool => (bool) $get('execute_javascript')),
                        TextInput::make('node_binary_path')
                            ->label(__('filament-sitemap-generator::page.settings_node_binary_path'))
                            ->visible(fn (Get $get): bool => (bool) $get('execute_javascript')),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => (bool) $get('crawl_enabled')),
                Section::make(__('filament-sitemap-generator::page.settings_automation'))
                    ->schema([
                        Toggle::make('auto_generate_enabled')
                            ->label(__('filament-sitemap-generator::page.settings_auto_generate_enabled'))
                            ->default(false)
                            ->live(),
                        Select::make('auto_generate_frequency')
                            ->label(__('filament-sitemap-generator::page.settings_auto_generate_frequency'))
                            ->options([
                                'hourly' => __('filament-sitemap-generator::page.settings_frequency_hourly'),
                                'daily' => __('filament-sitemap-generator::page.settings_frequency_daily'),
                            ])
                            ->nullable()
                            ->visible(fn ($get) => (bool) $get('auto_generate_enabled')),
                        Toggle::make('large_site_mode')
                            ->label(__('filament-sitemap-generator::page.settings_large_site_mode'))
                            ->default(false)
                            ->live(),
                        TextInput::make('chunk_size')
                            ->label(__('filament-sitemap-generator::page.settings_chunk_size'))
                            ->helperText(__('filament-sitemap-generator::page.settings_chunk_size_help'))
                            ->integer()
                            ->minValue(100)
                            ->default(1000)
                            ->required()
                            ->visible(fn ($get) => (bool) $get('large_site_mode')),
                    ])
                    ->columns(2),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    protected function fillForm(): void
    {
        $settings = SitemapSetting::getSettings();
        $this->form->fill(array_merge($this->getDefaultData(), $settings->toArray()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultData(): array
    {
        $config = config('filament-sitemap-generator', []);
        $staticUrls = $config['static_urls'] ?? [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']];
        $models = [];
        foreach ($config['models'] ?? [] as $class => $opts) {
            $models[] = [
                'model_class' => $class,
                'url_resolver_method' => $opts['url_resolver_method'] ?? 'getSitemapUrl',
                'changefreq' => $opts['changefreq'] ?? null,
                'priority' => $opts['priority'] ?? null,
                'enabled' => true,
            ];
        }

        $output = $config['output'] ?? [];
        $crawl = $config['crawl'] ?? [];

        return [
            'static_urls' => $staticUrls,
            'models' => $models,
            'default_change_frequency' => 'weekly',
            'default_priority' => 0.8,
            'auto_generate_enabled' => false,
            'auto_generate_frequency' => 'daily',
            'storage_path' => 'public',
            'filename' => 'sitemap.xml',
            'gzip_enabled' => false,
            'chunk_size' => 1000,
            'large_site_mode' => false,
            'enable_index_sitemap' => false,
            'output_mode' => $output['mode'] ?? 'file',
            'file_path' => $output['file_path'] ?? public_path('sitemap.xml'),
            'disk' => $output['disk'] ?? 'public',
            'disk_path' => $output['disk_path'] ?? 'sitemap.xml',
            'visibility' => $output['visibility'] ?? 'public',
            'crawl_enabled' => (bool) ($crawl['enabled'] ?? false),
            'crawl_url' => $crawl['url'] ?? null,
            'concurrency' => (int) ($crawl['concurrency'] ?? 10),
            'max_count' => $crawl['max_count'] ?? null,
            'maximum_depth' => $crawl['maximum_depth'] ?? null,
            'exclude_patterns' => $crawl['exclude_patterns'] ?? [],
            'crawl_profile' => $crawl['crawl_profile'] ?? null,
            'should_crawl' => $crawl['should_crawl'] ?? null,
            'has_crawled' => $crawl['has_crawled'] ?? null,
            'execute_javascript' => (bool) ($crawl['execute_javascript'] ?? false),
            'chrome_binary_path' => $crawl['chrome_binary_path'] ?? null,
            'node_binary_path' => $crawl['node_binary_path'] ?? null,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            $data = $this->mutateFormDataBeforeSave($data);

            if (! $this->settingsTableExists()) {
                $this->rollBackDatabaseTransaction();
                Notification::make()
                    ->title(__('filament-sitemap-generator::page.notification_failed'))
                    ->body(__('filament-sitemap-generator::page.settings_table_missing'))
                    ->danger()
                    ->send();

                return;
            }

            $settings = SitemapSetting::getSettings();
            $columns = array_flip(SchemaFacade::getColumnListing($settings->getTable()));
            $data = array_intersect_key($data, $columns);
            $settings->update($data);

            $this->commitDatabaseTransaction();

            Notification::make()
                ->title(__('filament-sitemap-generator::page.settings_saved'))
                ->success()
                ->send();
        } catch (Halt $exception) {
            $this->rollBackDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['chunk_size'])) {
            $data['chunk_size'] = max(100, (int) $data['chunk_size']);
        }
        if (isset($data['exclude_patterns']) && ! is_array($data['exclude_patterns'])) {
            $data['exclude_patterns'] = is_string($data['exclude_patterns'])
                ? array_values(array_filter(array_map('trim', explode("\n", $data['exclude_patterns']))))
                : [];
        }
        if (array_key_exists('concurrency', $data) && $data['concurrency'] !== null) {
            $data['concurrency'] = (int) $data['concurrency'];
        }
        if (array_key_exists('max_count', $data)) {
            $data['max_count'] = $data['max_count'] === null || $data['max_count'] === '' ? null : (int) $data['max_count'];
        }
        if (array_key_exists('maximum_depth', $data)) {
            $data['maximum_depth'] = $data['maximum_depth'] === null || $data['maximum_depth'] === '' ? null : (int) $data['maximum_depth'];
        }

        return $data;
    }

    private function settingsTableExists(): bool
    {
        return SchemaFacade::hasTable('sitemap_settings');
    }
}
