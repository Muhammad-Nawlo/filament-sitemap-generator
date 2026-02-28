<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

class TestPostWithInvalidVideos extends TestPostWithSitemapUrl
{
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories\TestPostWithInvalidVideosFactory::new();
    }

    public function getSitemapVideos(): array
    {
        return [
            [], // empty
            ['title' => 'No thumbnail or content'], // missing required
            ['thumbnail_loc' => 'https://example.com/t.jpg', 'title' => 'X', 'description' => 'Y'], // no content_loc or player_loc
            'not-an-array', // wrong type (will be skipped by is_array check)
        ];
    }
}
