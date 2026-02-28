<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

class TestPostSitemapableIterable extends TestPost implements Sitemapable
{
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories\TestPostSitemapableIterableFactory::new();
    }

    public function toSitemapTag(): Url | string | array
    {
        return [
            Url::create('https://example.com/posts/' . $this->id),
            Url::create('https://example.com/posts/' . $this->id . '/print'),
        ];
    }
}
