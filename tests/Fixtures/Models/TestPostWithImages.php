<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

class TestPostWithImages extends TestPostWithSitemapUrl
{
    public function getSitemapImages(): array
    {
        return [
            ['url' => 'https://example.com/images/' . $this->id . '.jpg', 'caption' => 'Image for ' . $this->title],
        ];
    }
}
