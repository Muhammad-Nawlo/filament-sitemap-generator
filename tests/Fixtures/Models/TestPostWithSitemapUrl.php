<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

class TestPostWithSitemapUrl extends TestPost
{
    public function getSitemapUrl(): string
    {
        return 'https://example.com/posts/' . $this->id;
    }
}
