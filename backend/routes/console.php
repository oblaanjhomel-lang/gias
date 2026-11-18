<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminders: overdue reports, signatures, evaluation windows / reviews, Monday digest.
// Locally: `php artisan schedule:work`. In production: a cron entry for `schedule:run`.
Schedule::command('gias:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
