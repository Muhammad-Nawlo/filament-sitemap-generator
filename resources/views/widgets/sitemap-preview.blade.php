<x-filament-widgets::widget>
    @php
        $data = $this->getPreviewData();
        $exists = $data['exists'];
        $content = $data['content'];
        $fileSize = $data['file_size'];
        $lastModified = $data['last_modified'];
        $validation = $data['validation'];
        $fileSizeKb = $fileSize > 0 ? (int) round($fileSize / 1024) : 0;
        $validationColor = match ($validation['status']) {
            'valid' => 'success',
            'invalid' => 'warning',
            'missing' => 'gray',
            default => 'gray',
        };
    @endphp

    <div
        x-data="{
            copied: false,
            async copyFromPreview() {
                const pre = document.getElementById('sitemap-preview-content-{{ $this->getId() }}');
                if (!pre) {
                    console.warn('Preview element not found');
                    return;
                }
                const text = pre.innerText;

                if (navigator.clipboard && window.isSecureContext) {
                    try {
                        await navigator.clipboard.writeText(text);
                        this.showCopiedFeedback();
                        return;
                    } catch (err) {
                        console.warn('Clipboard API failed, trying fallback:', err);
                    }
                }

                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                ta.setSelectionRange(0, text.length);

                let copySuccess = false;
                try {
                    copySuccess = document.execCommand('copy');
                    if (copySuccess) this.showCopiedFeedback();
                } catch (e) {
                    console.error('execCommand threw an error:', e);
                } finally {
                    document.body.removeChild(ta);
                }

                if (!copySuccess) {
                    prompt('Manual copy – press Ctrl+C (or Cmd+C) then Enter', text);
                }
            },
            showCopiedFeedback() {
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            }
        }"
    >
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="order-1 w-full">   {{-- Added w-full here --}}
                        {{ __('filament-sitemap-generator::page.tab_preview') }}
                        <x-filament::badge :color="$validationColor">
                            @if ($validation['status'] === 'valid')
                                {{ __('filament-sitemap-generator::page.validation_valid') }}
                            @elseif ($validation['status'] === 'invalid')
                                {{ __('filament-sitemap-generator::page.validation_invalid_label') }}
                            @else
                                {{ __('filament-sitemap-generator::page.validation_missing_label') }}
                            @endif
                        </x-filament::badge>
                    </p>
                    <div  style="display: flex;align-items: center;justify-content: space-between;">


                        @if ($exists)
                            <span class="text-sm text-gray-500 dark:text-gray-400 order-2">
                {{ __('filament-sitemap-generator::page.preview_file_size') }}: {{ number_format($fileSizeKb) }} KB
                @if ($lastModified)
                                    · {{ __('filament-sitemap-generator::page.preview_last_modified') }}
                                    : {{ $lastModified->format('Y-m-d H:i') }}
                                @endif
            </span>
                        @endif
                        <div class="flex items-center gap-2 flex-wrap order-3">
                            @if ($exists && $content !== null)
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-clipboard-document"
                                                    @click="copyFromPreview()"
                                                    x-bind:disabled="copied">
                                    <span x-text="copied ? '{{ __('filament-sitemap-generator::page.preview_copied') }}' : '{{ __('filament-sitemap-generator::page.preview_copy') }}'" ></span>
                                </x-filament::button>
                            @endif
                            @if ($exists)
                                <x-filament::button size="sm" color="gray" tag="a" :href="$this->getSitemapUrl()"
                                                    target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                                    {{ __('filament-sitemap-generator::page.action_preview') }}
                                </x-filament::button>
                            @endif
                            @if ($exists)
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-check-circle"
                                                    wire:click="runValidate">
                                    {{ __('filament-sitemap-generator::page.action_validate') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            </x-slot>

            @if (!$exists)
                <div
                    class="rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 px-4 py-6 text-center">
                    <p class="text-sm font-medium text-amber-600 dark:text-amber-400">
                        {{ __('filament-sitemap-generator::page.preview_warning_missing') }}
                    </p>
                </div>
            @elseif ($content === null)
                <div
                    class="rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 px-4 py-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-sitemap-generator::page.validation_read_failed') }}</p>
                </div>
            @else
                <div
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 overflow-hidden">
                    <pre
                        id="sitemap-preview-content-{{ $this->getId() }}"
                        class="p-4 text-xs font-mono text-gray-700 dark:text-gray-300 overflow-auto max-h-[70vh] whitespace-pre-wrap break-all">{{   $content }} </pre>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
