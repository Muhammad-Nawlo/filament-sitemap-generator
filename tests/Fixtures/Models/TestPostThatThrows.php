<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class TestPostThatThrows extends TestPost
{
    public function newEloquentBuilder($query): Builder
    {
        throw new RuntimeException('Intentional query failure for testing');
    }
}
