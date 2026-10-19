<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The approver reminders and overdue alerts. Needs `php artisan schedule:work`
// (or a per-minute cron running `schedule:run`) in production; see the
// deployment note in .ai/guidelines/sdao.md.
Schedule::command('documents:send-step-reminders')
    ->hourly()
    ->withoutOverlapping(10);
