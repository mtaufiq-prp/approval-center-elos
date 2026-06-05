<?php

namespace App\Console\Commands;

use App\Jobs\StartProcessJob;
use App\Models\TblApprovalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * approval:reconcile-stuck
 *
 * Jaring pengaman mode async-start (#H5 / risiko reliabilitas): bila StartProcessJob
 * gagal di-dispatch (queue driver mati setelah commit) atau hilang sebelum dieksekusi,
 * sebuah request bisa menggantung 'SUBMITTED' tanpa process instance & tanpa task.
 *
 * Command ini menemukan request SUBMITTED yang lebih tua dari --minutes dan BELUM
 * punya instance, lalu men-dispatch ulang StartProcessJob (idempoten: job akan skip
 * bila instance sudah ada / status bukan SUBMITTED).
 *
 * Dijadwalkan tiap 5 menit (routes/console.php). Aman dijalankan walau mode sinkron
 * (tidak akan menemukan kandidat karena instance dibuat dalam transaksi submit).
 */
class ReconcileStuckRequestsCommand extends Command
{
    protected $signature = 'approval:reconcile-stuck
                            {--minutes=10 : Umur minimal (menit) request SUBMITTED tanpa instance}
                            {--limit=500 : Maksimum request yang diproses per run}
                            {--dry-run : Hanya tampilkan, jangan dispatch}';

    protected $description = 'Re-drive request SUBMITTED yang menggantung tanpa process instance (safety net async-start)';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit   = max(1, (int) $this->option('limit'));
        $dryRun  = (bool) $this->option('dry-run');
        $threshold = now()->subMinutes($minutes);

        $ids = TblApprovalRequest::where('request_status', 'SUBMITTED')
            ->where('submitted_at', '<', $threshold)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tblprocess_instance')
                    ->whereColumn('tblprocess_instance.idtblapproval_request', 'tblapproval_request.idtblapproval_request');
            })
            ->orderBy('idtblapproval_request')
            ->limit($limit)
            ->pluck('idtblapproval_request');

        if ($ids->isEmpty()) {
            $this->info('Tidak ada request SUBMITTED yang menggantung.');
            return self::SUCCESS;
        }

        $this->warn(($dryRun ? '[DRY-RUN] ' : '') . "Menemukan {$ids->count()} request SUBMITTED tanpa instance (> {$minutes} menit).");

        foreach ($ids as $id) {
            if ($dryRun) {
                $this->line("  would re-dispatch StartProcessJob #{$id}");
                continue;
            }
            StartProcessJob::dispatch($id);
            $this->line("  re-dispatched StartProcessJob #{$id}");
        }

        if (! $dryRun) {
            Log::warning("approval:reconcile-stuck re-dispatched {$ids->count()} stuck SUBMITTED request(s): " . $ids->implode(','));
        }

        return self::SUCCESS;
    }
}
