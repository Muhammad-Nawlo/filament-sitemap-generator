<?php

declare(strict_types=1);

namespace MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MuhammadNawlo\FilamentSitemapGenerator\Tests\Fixtures\Models\TestPost;

class TestPostFactory extends Factory
{
    protected $model = TestPost::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'published_at' => fake()->optional(0.8)->dateTimeBetween('-1 year'),
        ];
    }
}
