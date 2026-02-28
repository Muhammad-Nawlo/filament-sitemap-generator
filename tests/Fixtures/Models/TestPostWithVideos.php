<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

class TestPostWithVideos extends TestPostWithSitemapUrl
{
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories\TestPostWithVideosFactory::new();
    }

    public function getSitemapVideos(): array
    {
        return [
            [
                'thumbnail_loc' => 'https://example.com/thumbs/' . $this->id . '.jpg',
                'title' => 'Video: ' . $this->title,
                'description' => 'Description for video ' . $this->id,
                'content_loc' => 'https://example.com/videos/' . $this->id . '.mp4',
            ],
        ];
    }
}
