<?php

use MuhammadNawlo\FilamentSitemapGenerator\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

beforeEach(function (): void {
    config()->set('filament-sitemap-generator.path', storage_path('framework/testing/sitemap.xml'));
});
