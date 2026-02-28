<x-filament-widgets::widget>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">

            <x-filament::badge :color="$autoGenerationEnabled ? 'success' : 'gray'">
                {{ __('filament-sitemap-generator::page.auto_generation_badge') }}:
                {{ $autoGenerationEnabled ? __('filament-sitemap-generator::page.auto_generation_enabled') : __('filament-sitemap-generator::page.auto_generation_disabled') }}
            </x-filament::badge>
        </div>

        @if ($showNoStaticUrlsWarning)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                {{ __('filament-sitemap-generator::page.warning_no_static_urls') }}
                <a href="{{ $settingsUrl }}" class="font-medium underline">{{ __('filament-sitemap-generator::page.open_settings') }}</a>
            </div>
        @endif

        @if ($showNoModelsWarning)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                {{ __('filament-sitemap-generator::page.warning_no_models') }}
                <a href="{{ $settingsUrl }}" class="font-medium underline">{{ __('filament-sitemap-generator::page.open_settings') }}</a>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
