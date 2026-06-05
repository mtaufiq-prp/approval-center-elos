<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * approval:prune-logs
 *
 * Retensi log OPERASIONAL bervolume tinggi agar tabel tidak tumbuh tak terbatas
 * pada beban 1000 req/menit (~1.4M+ baris/hari). Menghapus baris lebih tua dari
 * --days secara CHUNK (hindari transaksi panjang & lock besar).
 *
 * Tabel yang di-prune (operasional, bukan audit kepatuhan):
 *   - tblprocess_route_log         (jejak traversal engine; paling ramai)
 *   - tblintegration_message_log   (request/response API inbound)
 *   - tblcallback_outbox           HANYA status SENT/DEAD (PENDING/FAILED dipertahankan)
 *
 * TIDAK di-prune: tblaudit_event & tblaction_log (append-only, retensi audit panjang).
 *
 * Dijadwalkan harian (routes/console.php). Sesuaikan retensi via --days atau env.
 */
class PruneOperationalLogsCommand extends Command
{
    protected $signature = 'approval:prune-logs
                            {--days=30 : Hapus baris lebih tua dari N hari}
                            {--chunk=5000 : Jumlah baris per batch delete}
                            {--dry-run : Hanya hitung, jangan hapus}';

    protected $description = 'Prune log operasional bervolume tinggi (route_log, integration_log, callback SENT/DEAD)';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $chunk  = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $targets = [
            ['table' => 'tblprocess_route_log',       'where' => fn ($q) => $q->where('created_at', '<', $cutoff)],
            ['table' => 'tblintegration_message_log', 'where' => fn ($q) => $q->where('created_at', '<', $cutoff)],
            ['table' => 'tblcallback_outbox',         'where' => fn ($q) => $q->where('created_at', '<', $cutoff)->whereIn('status', ['SENT', 'DEAD'])],
        ];

        $grand = 0;
        foreach ($targets as $t) {
            $base = fn () => $t['where'](DB::table($t['table']));

            if ($dryRun) {
                $count = $base()->count();
                $this->line("[DRY-RUN] {$t['table']}: {$count} baris > {$days} hari");
                $grand += $count;
                continue;
            }

            $deleted = 0;
            do {
                $n = $base()->limit($chunk)->delete();
                $deleted += $n;
            } while ($n === $chunk);

            $this->info("{$t['table']}: {$deleted} baris dihapus (> {$days} hari)");
            $grand += $deleted;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Total: {$grand} baris.");
        return self::SUCCESS;
    }
}
