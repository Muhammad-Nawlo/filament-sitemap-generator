<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

use DateTimeInterface;

class TestNewsPost extends TestPostWithSitemapUrl
{
    public function getSitemapNewsTitle(): string
    {
        return $this->title;
    }

    public function getSitemapNewsPublicationDate(): ?DateTimeInterface
    {
        return $this->published_at ?? $this->updated_at;
    }
}
