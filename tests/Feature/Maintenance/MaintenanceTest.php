<?php

namespace Tests\Feature\Maintenance;

use App\Jobs\StartProcessJob;
use App\Services\FlowEngineService;
use App\Services\PayloadEnrichmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * Rekomendasi lanjutan:
 *  - approval:reconcile-stuck  (jaring pengaman async-start)
 *  - approval:prune-logs       (retensi log operasional)
 *  - cache enrichment cross-DB (perf)
 */
class MaintenanceTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    public function test_reconcile_redispatches_only_stuck_submitted_without_instance(): void
    {
        Queue::fake();
        $fx = $this->buildMinimalFlow();

        // (1) Stuck: SUBMITTED, lama, tanpa instance → kandidat.
        $stuck = $this->makeApprovalRequest($fx, null, 'DOC-STUCK');
        $stuck->submitted_at = now()->subMinutes(30);
        $stuck->save();

        // (2) Fresh: SUBMITTED tapi baru → bukan kandidat (submitted_at = now).
        $this->makeApprovalRequest($fx, null, 'DOC-FRESH');

        // (3) Sudah berjalan: punya instance (IN_PROGRESS) → bukan kandidat.
        $running = $this->makeApprovalRequest($fx, null, 'DOC-RUN');
        $running->submitted_at = now()->subMinutes(30);
        $running->save();
        app(FlowEngineService::class)->startProcess($running, $fx['version']);

        $this->artisan('approval:reconcile-stuck', ['--minutes' => 10])->assertExitCode(0);

        Queue::assertPushed(StartProcessJob::class, 1);
        Queue::assertPushed(StartProcessJob::class, function (StartProcessJob $job) use ($stuck) {
            $r = new \ReflectionProperty($job, 'requestId');
            return (int) $r->getValue($job) === (int) $stuck->idtblapproval_request;
        });
    }

    public function test_prune_logs_removes_old_keeps_recent(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeApprovalRequest($fx, null, 'DOC-PRUNE');
        // startProcess membuat route log "baru" (created_at = now).
        app(FlowEngineService::class)->startProcess($req, $fx['version']);

        $recentRoute = DB::table('tblprocess_route_log')->where('created_at', '>=', now()->subDays(30))->count();
        $this->assertGreaterThan(0, $recentRoute, 'startProcess harus menghasilkan route log baru.');

        // Sisipkan baris LAMA (40 hari) untuk route_log & integration_log.
        DB::table('tblprocess_route_log')->insert([
            'idtblapproval_request' => $req->idtblapproval_request,
            'idtblprocess_instance' => DB::table('tblprocess_instance')->where('idtblapproval_request', $req->idtblapproval_request)->value('idtblprocess_instance'),
            'route_event'           => 'ENTER_NODE',
            'created_at'            => now()->subDays(40),
        ]);
        DB::table('tblintegration_message_log')->insert([
            'idtblsource_app' => $fx['app']->idtblsource_app,
            'direction'       => 'INBOUND',
            'status'          => 'SUCCESS',
            'created_at'      => now()->subDays(40),
        ]);

        $oldRouteBefore = DB::table('tblprocess_route_log')->where('created_at', '<', now()->subDays(30))->count();
        $oldIntBefore   = DB::table('tblintegration_message_log')->where('created_at', '<', now()->subDays(30))->count();
        $this->assertSame(1, $oldRouteBefore);
        $this->assertSame(1, $oldIntBefore);

        $this->artisan('approval:prune-logs', ['--days' => 30])->assertExitCode(0);

        $this->assertSame(0, DB::table('tblprocess_route_log')->where('created_at', '<', now()->subDays(30))->count(),
            'Route log lama harus terhapus.');
        $this->assertSame(0, DB::table('tblintegration_message_log')->where('created_at', '<', now()->subDays(30))->count(),
            'Integration log lama harus terhapus.');
        $this->assertGreaterThan(0, DB::table('tblprocess_route_log')->where('created_at', '>=', now()->subDays(30))->count(),
            'Route log baru harus dipertahankan.');
    }

    public function test_enrichment_cross_db_lookup_is_cached(): void
    {
        config(['approval_center.enrichment.cache_ttl_seconds' => 300]);
        Cache::flush();

        DB::statement("INSERT INTO db_master.ms_product_group (ph4, produk_manager, pd_manager) VALUES ('CACHEPH', 'PMM1', 'PD1')");

        $svc = new PayloadEnrichmentService();
        $payload = ['header' => [['idtblbranch' => 'NOPE']], 'detail' => [['ph' => 'CACHEPH']]];

        $c1 = $svc->enrich(['k' => 1], $payload, 'SFA');
        $this->assertSame('PMM1', $c1['_computed']['pmm_user_ref'] ?? null);

        // Ubah baris master; panggilan kedua HARUS tetap nilai lama (dari cache).
        DB::statement("UPDATE db_master.ms_product_group SET produk_manager = 'PMM2' WHERE ph4 = 'CACHEPH'");
        $c2 = $svc->enrich(['k' => 1], $payload, 'SFA');
        $this->assertSame('PMM1', $c2['_computed']['pmm_user_ref'] ?? null, 'Lookup harus dilayani dari cache (nilai lama).');

        // Setelah cache di-flush → ambil nilai baru.
        Cache::flush();
        $c3 = $svc->enrich(['k' => 1], $payload, 'SFA');
        $this->assertSame('PMM2', $c3['_computed']['pmm_user_ref'] ?? null);

        // Bersih-bersih stub (DatabaseTransactions me-rollback connection default, tapi eksplisit aman).
        DB::statement("DELETE FROM db_master.ms_product_group WHERE ph4 = 'CACHEPH'");
    }
}
