<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler: jalan lewat `php artisan schedule:work` (dev)
// atau cron `schedule:run` (prod) — jadwal hidup di versi control,
// bukan di cron manager eksternal.
Schedule::command('orders:cleanup')->dailyAt('03:00');
