<?php

use App\Jobs\ProcessCallbackOutboxJob;
use App\Jobs\ProcessNotificationQueueJob;
use App\Jobs\SlaEscalationJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduler
|--------------------------------------------------------------------------
| Pastikan cron terdaftar di server:
|   * * * * * cd /var/www/approval-center && php artisan schedule:run >> /dev/null 2>&1
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proses callback outbox setiap 1 menit.
// Pakai queue 'default' agar dikonsumsi worker standar (php artisan queue:work) (#87).
Schedule::job(new ProcessCallbackOutboxJob)
    ->everyMinute()
    ->name('process-callback-outbox')
    ->withoutOverlapping(5);

// Proses antrian notifikasi setiap 1 menit (#88)
Schedule::job(new ProcessNotificationQueueJob)
    ->everyMinute()
    ->name('process-notification-queue')
    ->withoutOverlapping(5);

// SLA escalation setiap 30 menit
Schedule::job(new SlaEscalationJob, 'default')
    ->everyThirtyMinutes()
    ->name('sla-escalation')
    ->withoutOverlapping(10);
