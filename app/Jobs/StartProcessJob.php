<?php

namespace App\Jobs;

use App\Models\TblApprovalRequest;
use App\Models\TblFlowVersion;
use App\Models\TblProcessInstance;
use App\Services\FlowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * StartProcessJob — menjalankan flow engine secara ASINKRON untuk sebuah request
 * yang sudah dibuat (mode APPROVAL_ASYNC_START=true, review #12).
 *
 * Tujuan skalabilitas 1000 req/menit: memindahkan traversal engine + resolusi
 * assignee + pembuatan task keluar dari request HTTP submit. Endpoint submit hanya
 * menyimpan request (transaksional, ringan) lalu meng-enqueue job ini.
 *
 * Idempoten:
 *  - ShouldBeUnique per requestId → tidak ada dua job paralel untuk request sama.
 *  - Skip bila instance sudah ada (engine sudah pernah dijalankan) atau status
 *    request bukan SUBMITTED lagi.
 */
class StartProcessJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 10;

    public function __construct(private int $requestId) {}

    public function uniqueId(): string
    {
        return (string) $this->requestId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(FlowEngineService $engine): void
    {
        if (! TblApprovalRequest::whereKey($this->requestId)->exists()) {
            Log::warning("StartProcessJob: request #{$this->requestId} tidak ditemukan.");
            return;
        }

        // #R3: LOCK request + re-cek status di bawah lock agar tidak balapan dengan cancel.
        // Tanpa ini, cancel yang commit saat job in-flight bisa tertimpa kembali ke RUNNING
        // (request dibaca SUBMITTED lalu startProcess men-set IN_PROGRESS → un-cancel).
        // Request adalah satu-satunya baris yang dikontensi dengan cancel di state ini
        // (instance belum ada), jadi tidak ada ABBA. Lock dipegang selama startProcess
        // (transaksi engine nested sebagai savepoint).
        \Illuminate\Support\Facades\DB::transaction(function () use ($engine) {
            $request = TblApprovalRequest::where('idtblapproval_request', $this->requestId)
                ->lockForUpdate()->first();
            if (! $request) {
                return;
            }

            // Idempotensi: engine sudah pernah dijalankan untuk request ini.
            if (TblProcessInstance::where('idtblapproval_request', $request->idtblapproval_request)->exists()) {
                return;
            }

            // Hanya start untuk request yang masih SUBMITTED (mis. belum keburu di-cancel).
            if ($request->request_status !== 'SUBMITTED') {
                return;
            }

            $version = TblFlowVersion::find($request->idtblflow_version_selected);
            if (! $version) {
                Log::error("StartProcessJob: flow version #{$request->idtblflow_version_selected} untuk request #{$request->idtblapproval_request} tidak ditemukan.");
                $this->markError($request);
                return;
            }

            $engine->startProcess($request, $version);
        });
    }

    /** Dipanggil saat job benar-benar gagal (semua retry habis). */
    public function failed(\Throwable $e): void
    {
        Log::error("StartProcessJob #{$this->requestId} gagal permanen: {$e->getMessage()}");

        $request = TblApprovalRequest::find($this->requestId);
        if ($request
            && $request->request_status === 'SUBMITTED'
            && ! TblProcessInstance::where('idtblapproval_request', $request->idtblapproval_request)->exists()) {
            // Tandai ERROR agar tidak menggantung SUBMITTED tanpa task; bisa di-reset admin.
            $this->markError($request);
        }
    }

    private function markError(TblApprovalRequest $request): void
    {
        $request->request_status = 'ERROR';
        $request->save();
    }
}
