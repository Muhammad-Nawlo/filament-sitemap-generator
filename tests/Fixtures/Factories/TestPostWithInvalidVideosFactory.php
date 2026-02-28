<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories;

use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithInvalidVideos;

class TestPostWithInvalidVideosFactory extends TestPostWithSitemapUrlFactory
{
    protected $model = TestPostWithInvalidVideos::class;
}
