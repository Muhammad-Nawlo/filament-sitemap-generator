<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestPost extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = ['title', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories\TestPostFactory::new();
    }
}
