<?php

namespace App\Jobs;

use App\Models\TblCallbackOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scans pending callback outbox dan dispatch SendCallbackJob.
 * Dipanggil scheduler setiap 1 menit.
 */
class ProcessCallbackOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        TblCallbackOutbox::whereIn('status', ['PENDING', 'RETRY'])
            ->where('retry_count', '<', 5)
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->each(fn($cb) => SendCallbackJob::dispatch($cb->idtblcallback_outbox));
    }
}
