<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

use DateTimeInterface;

class TestPostWithLastmod extends TestPostWithSitemapUrl
{
    public function getSitemapLastModified(): ?DateTimeInterface
    {
        return $this->updated_at;
    }
}
