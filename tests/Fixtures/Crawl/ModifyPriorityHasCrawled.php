<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Crawl;

use Psr\Http\Message\ResponseInterface;
use Spatie\Sitemap\Tags\Url;

class ModifyPriorityHasCrawled
{
    public function __invoke(Url $url, ?ResponseInterface $response = null): Url
    {
        $url->setPriority(0.5);

        return $url;
    }
}
