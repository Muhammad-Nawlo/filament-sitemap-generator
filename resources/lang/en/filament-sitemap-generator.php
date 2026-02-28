<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Settings',
        'label' => 'Sitemap',
    ],

    'page' => [
        'title' => 'Sitemap Generator',
        'generate_button' => 'Generate Sitemap',
        'notification_success' => 'Sitemap generated successfully.',
        'notification_failed' => 'Sitemap generation failed',
    ],

    'command' => [
        'description' => 'Generate the sitemap',
        'dispatched' => 'Sitemap generation dispatched.',
        'success' => 'Sitemap generated successfully.',
        'failed' => 'Sitemap generation failed',
    ],
];
