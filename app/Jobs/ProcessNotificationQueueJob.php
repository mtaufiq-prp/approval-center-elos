<?php

namespace App\Jobs;

use App\Models\TblNotificationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessNotificationQueueJob — consumer antrian notifikasi (#88).
 *
 * Dijadwalkan tiap menit. Memproses baris PENDING:
 *  - IN_APP : cukup ditandai SENT (indikator di inbox web; tidak perlu kirim eksternal).
 *  - EMAIL/TELEGRAM/WHATSAPP/WEB_PUSH : belum ada integrasi → di-log & ditandai FAILED
 *    dengan pesan jelas (tidak silent), sampai channel diimplementasi.
 */
class ProcessNotificationQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        $rows = TblNotificationQueue::where('status', 'PENDING')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('next_retry_at')
            ->limit(100)
            ->get();

        foreach ($rows as $notif) {
            try {
                if ($notif->channel === 'IN_APP') {
                    $notif->status  = 'SENT';
                    $notif->sent_at = now();
                    $notif->error_message = null;
                    $notif->save();
                    continue;
                }

                // Channel eksternal belum diimplementasi — tandai jelas, jangan silent.
                $notif->status        = 'FAILED';
                $notif->error_message = "Channel {$notif->channel} belum diimplementasi.";
                $notif->save();
                Log::warning("Notification #{$notif->idtblnotification_queue}: channel {$notif->channel} belum didukung.");
            } catch (\Throwable $e) {
                $notif->status        = 'FAILED';
                $notif->error_message = mb_substr($e->getMessage(), 0, 1000);
                $notif->save();
                Log::error("ProcessNotificationQueueJob #{$notif->idtblnotification_queue}: {$e->getMessage()}");
            }
        }
    }
}
