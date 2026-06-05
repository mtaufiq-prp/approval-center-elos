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
        //
        // #6/#9/#10: batch dinaikkan & dapat dikonfigurasi. Pada 1000 req/menit,
        // ~1000 callback/menit diproduksi; limit 50 lama membuat backlog tumbuh
        // ~950/menit dan tak pernah terkuras. Dispatch hanya MENG-ENQUEUE job (ringan);
        // pengiriman HTTP dilakukan paralel oleh beberapa worker `queue:work`.
        // Gunakan chunk agar tidak memuat seluruh batch ke memori sekaligus.
        $batch = max(1, (int) config('approval_center.callback.batch_size', 1000));

        // Ambil hanya kolom id (ringan) hingga $batch baris, urut next_retry_at (terlama dulu).
        TblCallbackOutbox::readyForDispatch()
            ->limit($batch)
            ->pluck('idtblcallback_outbox')
            ->each(fn ($id) => SendCallbackJob::dispatch($id));
    }
}
