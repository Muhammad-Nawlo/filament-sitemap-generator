<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

it('registers command in schedule when schedule enabled', function (): void {
    $schedule = app(Schedule::class);
    $events = $schedule->events();
    $hasSitemapCommand = false;
    foreach ($events as $event) {
        $command = $event->command ?? '';
        if (str_contains($command, 'filament-sitemap-generator:generate')) {
            $hasSitemapCommand = true;
            break;
        }
    }
    expect($hasSitemapCommand)->toBeTrue('Expected schedule to contain filament-sitemap-generator:generate when schedule.enabled is true.');
});
