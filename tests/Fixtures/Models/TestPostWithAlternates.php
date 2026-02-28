<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

class TestPostWithAlternates extends TestPostWithSitemapUrl
{
    public function getAlternateUrls(): array
    {
        return [
            'en' => 'https://example.com/en/posts/' . $this->id,
            'fr' => 'https://example.com/fr/posts/' . $this->id,
        ];
    }
}
