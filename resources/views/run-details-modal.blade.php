@php
    /** @var \MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun $run */
    $fileSizeKb = $run->file_size > 0 ? (int) round($run->file_size / 1024) : 0;
@endphp
<dl class="fi-dl grid gap-4 sm:grid-cols-2">
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_status') }}</dt>
        <dd>
            <x-filament::badge :color="$run->isSuccess() ? 'success' : 'danger'">
                {{ $run->status }}
            </x-filament::badge>
        </dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_last_generated') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ $run->generated_at->format('M j, Y H:i:s') }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_total_urls') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ number_format($run->total_urls) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_static_urls') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ number_format($run->static_urls) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_model_urls') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ number_format($run->model_urls) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_file_size') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ number_format($fileSizeKb) }} KB</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.stats_duration') }}</dt>
        <dd class="text-sm text-gray-950 dark:text-white">{{ number_format($run->duration_ms) }} ms</dd>
    </div>
    @if ($run->error_message)
        <div class="sm:col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.run_error_message') }}</dt>
            <dd class="mt-1 text-sm text-gray-950 dark:text-white whitespace-pre-wrap rounded bg-gray-100 dark:bg-gray-800 p-2">{{ e($run->error_message) }}</dd>
        </div>
    @endif
</dl>
