<?php

declare(strict_types=1);

return [
    'path' => public_path('sitemap.xml'),

    'output' => [
        'mode' => 'file', // 'file' or 'disk'
        'file_path' => public_path('sitemap.xml'), // used when mode = 'file'
        'disk' => 'public',
        'disk_path' => 'sitemap.xml', // path on disk when mode = 'disk'
        'visibility' => 'public', // 'public' or 'private'
    ],

    'sitemap_run_model' => \MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapRun::class,

    'sitemap_runs_table' => 'sitemap_runs',

    'sitemap_settings_table' => 'sitemap_settings',

    'chunk_size' => 500,

    'max_urls_per_file' => 50000,

    'base_url' => null,

    'static_urls' => [
        [
            'url' => '/',
            'priority' => 1.0,
            'changefreq' => 'daily',
        ],
    ],

    'models' => [
        // App\Models\Post::class => [
        //     'priority' => 0.8,
        //     'changefreq' => 'weekly',
        //     'route' => 'posts.show', // required when model has no getSitemapUrl()
        // ],
    ],

    'schedule' => [
        'enabled' => false,
        'frequency' => 'daily',
    ],

    'queue' => [
        'enabled' => false,
        'connection' => null,
        'queue' => null,
    ],

    'news' => [
        'enabled' => false,
        'publication_name' => null,
        'publication_language' => 'en',
        'models' => [],
    ],

    'ping_search_engines' => [
        'enabled' => false,
        'engines' => [
            'google',
            'bing',
        ],
    ],

    'crawl' => [
        'enabled' => false,
        'url' => null,
        'concurrency' => 10,
        'max_count' => null,
        'max_tags_per_sitemap' => 50000,
        'exclude_patterns' => [],

        'maximum_depth' => null,
        'crawl_profile' => null,
        'should_crawl' => null,
        'has_crawled' => null,

        'execute_javascript' => false,
        'chrome_binary_path' => null,
        'node_binary_path' => null,
    ],
];
