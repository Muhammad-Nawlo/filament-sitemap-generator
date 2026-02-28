<?php

declare(strict_types=1);

return [
    'path' => public_path('sitemap.xml'),

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
];
