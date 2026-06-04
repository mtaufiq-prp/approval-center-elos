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
        // Ambil baris yang siap (PENDING/FAILED & next_retry_at <= now), urut next_retry_at.
        // SendCallbackJob ShouldBeUnique per id → aman dari dispatch ganda (#86).
        TblCallbackOutbox::readyForDispatch()
            ->limit(50)
            ->get()
            ->each(fn($cb) => SendCallbackJob::dispatch($cb->idtblcallback_outbox));
    }
}
