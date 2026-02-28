<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Crawl;

use Psr\Http\Message\UriInterface;

class AllowAllShouldCrawl
{
    public function __invoke(UriInterface $url): bool
    {
        return true;
    }
}
