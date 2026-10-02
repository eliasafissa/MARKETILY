<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$log = storage_path('logs/oranos-sync.log');

Schedule::command('oranos:sync-categories')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:sync-products')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:harvest --update')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:apply-markup')
    ->weekly()
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:verify-price-sync')
    ->weekly()
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('orders:poll-oranos')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/oranos-orders.log'));

Schedule::command('oranos:import-category-images')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:import-product-images')
    ->dailyAt('03:50')
    ->withoutOverlapping()
    ->appendOutputTo($log);

Schedule::command('oranos:sync-product-images')
    ->dailyAt('04:20')
    ->withoutOverlapping()
    ->appendOutputTo($log);
