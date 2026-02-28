<?php

namespace MuhammadNawlo\FilamentSitemapGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MuhammadNawlo\FilamentSitemapGenerator\FilamentSitemapGenerator
 */
class FilamentSitemapGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MuhammadNawlo\FilamentSitemapGenerator\FilamentSitemapGenerator::class;
    }
}
