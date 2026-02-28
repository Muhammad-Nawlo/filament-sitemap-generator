<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories;

use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPostWithVideos;

class TestPostWithVideosFactory extends TestPostWithSitemapUrlFactory
{
    protected $model = TestPostWithVideos::class;
}
