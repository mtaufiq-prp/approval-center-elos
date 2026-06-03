<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TblApprovalRequest;
use App\Models\TblProcessInstance;
use App\Models\TblFlowStep;
use App\Models\TblFlowVersion;
use App\Models\TblFlowTransition;
use App\Models\TblTask;
use App\Models\TblTaskCandidate;
use App\Models\TblSourceApp;
use App\Models\TblDocumentType;
use App\Models\TblUser;
use App\Models\TblProcessRouteLog;
use App\Services\PayloadEnrichmentService;

/**
 * SfaReturFlowV2TrialSeeder
 *
 * Membuat 4 approval request trial yang merepresentasikan jalur berbeda
 * pada Flow V2 FLOW_SFA_RETUR:
 *
 *  Trial 1 — JALUR P4 (≤5jt)          : Menunggu BMH saja
 *  Trial 2 — JALUR P3 (5-15jt)         : BMH sudah approve, menunggu RRM
 *  Trial 3 — JALUR P6 (idmsalasan 61)  : Baru masuk, menunggu BMH
 *             (jika approve sampai selesai: BMH→RRM→NRM→PMM→PD→CEO)
 *  Trial 4 — JALUR P7 (idmsalasan 33)  : Baru masuk, menunggu BMH
 *             (jika approve sampai selesai: BMH→RRM→NRM→PKG→CEO)
 *
 * Jalankan: php artisan db:seed --class=SfaReturFlowV2TrialSeeder
 */
class SfaReturFlowV2TrialSeeder extends Seeder
{
    // NPK yang sudah kita seeder
    private const BRANCH_1401 = '1401'; // TANGERANG
    private const BRANCH_1403 = '1403'; // SEMARANG

    private const BMH_1401 = '11110247'; // SLAMET SANTOSO - Tangerang
    private const RRM_1401 = '11030021'; // MOH. CARNO ADINATA

    private const BMH_1403 = '11150116'; // DWI HARYANTO - Semarang
    private const RRM_1403 = '11020031'; // AGUS WIDJAJA

    private const NRM      = '11990056'; // JULIUS KURATA
    private const CEO      = '1030018';  // KRIS RIANTO ADIDARMA
    private const PKG      = '11130476'; // HENDRI GUNAWAN

    // PH untuk produk Propan - akan di-lookup PMM/PD dari ms_product_group
    private const PH_SAMPLE = 'BH0702';

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  SFA Retur Flow V2 Trial Seeder');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // ── Resolve master data ──────────────────────────────────────────
        $sourceApp = TblSourceApp::where('app_code', 'SFA')->first();
        if (! $sourceApp) { $this->command->error('Source App SFA tidak ditemukan!'); return; }

        $docType = TblDocumentType::where('doc_code', 'SFA_R1')
            ->where('idtblsource_app', $sourceApp->idtblsource_app)->first();
        if (! $docType) { $this->command->error('Document Type SFA_R1 tidak ditemukan!'); return; }

        // Cari flow version V2 yang sudah ACTIVE
        $flowVersion = TblFlowVersion::whereHas('flowDefinition', function ($q) use ($sourceApp) {
                $q->where('idtblsource_app', $sourceApp->idtblsource_app);
            })
            ->where('status', 'ACTIVE')
            ->where('version_no', 2)
            ->first();

        if (! $flowVersion) {
            // Fallback: ambil yang paling baru ACTIVE
            $flowVersion = TblFlowVersion::whereHas('flowDefinition', function ($q) use ($sourceApp) {
                $q->where('idtblsource_app', $sourceApp->idtblsource_app);
            })->where('status', 'ACTIVE')->latest()->first();
        }

        if (! $flowVersion) {
            $this->command->error('Tidak ada Flow Version ACTIVE! Deploy dulu dari Visual Builder.');
            return;
        }

        $this->command->line("  Source App : {$sourceApp->app_code}");
        $this->command->line("  Doc Type   : {$docType->doc_code}");
        $this->command->line("  Flow       : {$flowVersion->version_name} (id={$flowVersion->idtblflow_version})");

        // Load semua nodes
        $nodes = TblFlowStep::where('idtblflow_version', $flowVersion->idtblflow_version)
            ->get()->keyBy('node_code');

        if ($nodes->isEmpty()) {
            $this->command->error('Tidak ada nodes di flow ini!');
            return;
        }

        // Resolve user objects
        $users = $this->resolveUsers();

        // Lookup PMM & PD dari ms_product_group
        [$pmmRef, $pdRef] = $this->lookupPmmPd(self::PH_SAMPLE);
        $this->command->line("  PMM ref    : " . ($pmmRef ?? '(tidak ditemukan)'));
        $this->command->line("  PD ref     : " . ($pdRef  ?? '(tidak ditemukan)'));

        // ── Buat 4 trial requests ────────────────────────────────────────
        $trials = $this->buildTrials($pmmRef, $pdRef);
        $created = 0;

        foreach ($trials as $t) {
            $this->command->info('');
            $this->command->info("  ▶ [{$t['jalur']}] {$t['doc_ref']}");
            $this->command->line("    {$t['title']}");

            $exists = TblApprovalRequest::where('source_request_id', $t['doc_ref'])
                ->where('idtblsource_app', $sourceApp->idtblsource_app)->exists();
            if ($exists) { $this->command->warn('    Skip — sudah ada.'); continue; }

            DB::transaction(function () use (
                $t, $sourceApp, $docType, $flowVersion, $nodes, $users, $pmmRef, $pdRef
            ) {
                $submittedAt = Carbon::now()->subDays($t['days_ago']);

                // ── Buat approval request ─────────────────────────────
                $req = TblApprovalRequest::create([
                    'idtblsource_app'            => $sourceApp->idtblsource_app,
                    'idtbldocument_type'          => $docType->idtbldocument_type,
                    'source_request_id'           => $t['doc_ref'],
                    'source_request_no'           => $t['source_no'],
                    'idempotency_key'             => 'v2trial_' . $t['doc_ref'],
                    'title'                       => $t['title'],
                    'requester_ref'               => $t['requester_ref'],
                    'requester_name'              => $t['requester_name'],
                    'requester_org_code'          => $t['org_code'],
                    'requester_org_name'          => $t['org_name'],
                    'amount'                      => $t['amount'],
                    'currency_code'               => 'IDR',
                    'priority'                    => $t['priority'],
                    'request_status'              => 'IN_PROGRESS',
                    'source_status'               => $t['source_status'],
                    'callback_url'                => 'http://sfa.propan.internal/approval/callback',
                    'context_json'                => $t['context'],
                    'payload_json'                => $t['payload'],
                    'idtblflow_version_selected'  => $flowVersion->idtblflow_version,
                    'idtblflow_step_current'      => $nodes[$t['current_node']]->idtblflow_step,
                    'submitted_at'                => $submittedAt,
                ]);

                // ── Buat process instance ─────────────────────────────
                $instance = TblProcessInstance::create([
                    'idtblapproval_request'   => $req->idtblapproval_request,
                    'idtblflow_version'       => $flowVersion->idtblflow_version,
                    'instance_status'         => 'RUNNING',
                    'idtblflow_step_current'  => $nodes[$t['current_node']]->idtblflow_step,
                    'started_at'              => $submittedAt,
                ]);

                // ── Route logs ────────────────────────────────────────
                $this->createLogs($req, $instance, $flowVersion, $nodes, $t, $submittedAt, $users);

                // ── Task aktif di current node ────────────────────────
                $this->createActiveTask($req, $instance, $nodes[$t['current_node']], $t, $users);

                $this->command->info("    ✓ Request #{$req->idtblapproval_request} dibuat");
            });
            $created++;
        }

        // ── Print panduan login ──────────────────────────────────────────
        $this->printLoginGuide($trials, $pmmRef, $pdRef);
    }

    // ── Build trial data ──────────────────────────────────────────────────

    private function buildTrials(?string $pmmRef, ?string $pdRef): array
    {
        return [
            // ── Trial 1: JALUR P4 — nilai ≤5jt, bukan alasan B ───────────
            // Hanya butuh approval BMH
            [
                'jalur'        => 'P4 — ≤5jt — BMH saja',
                'doc_ref'      => 'V2-TRIAL-P4-001',
                'source_no'    => 'RT-V2P4001',
                'title'        => '[P4] Retur Kemasan Rusak — UD. MAKMUR / TANGERANG — Rp 2.500.000',
                'requester_ref'=> '11110247',
                'requester_name'=> 'SLAMET SANTOSO',
                'org_code'     => '1401',
                'org_name'     => 'TANGERANG',
                'amount'       => 2500000,
                'priority'     => 'NORMAL',
                'days_ago'     => 1,
                'source_status'=> 'MENUNGGU PERSETUJUAN BMH',
                'current_node' => 'BMH',
                'completed_nodes' => [],
                'context' => [
                    'customer_name'  => 'UD. MAKMUR SEJATI',
                    'branch_name'    => 'TANGERANG',
                    'employee_name'  => 'SUNARDI',
                    'alasan_retur'   => 'KEMASAN RUSAK',
                    'nilai_retur'    => '2500000',
                    'status'         => 'MENUNGGU PERSETUJUAN BMH',
                    '_computed' => [
                        'total_nilai_retur' => 2500000,
                        'idmsalasan_list'   => [3],  // kemasan rusak biasa - bukan P6/P7
                        'bmh_user_ref'      => self::BMH_1401,
                        'bmh_user_refs'     => [self::BMH_1401, '11080038', '11130010'],
                        'rrm_user_ref'      => self::RRM_1401,
                        'nrm_user_ref'      => self::NRM,
                        'ceo_user_ref'      => self::CEO,
                        'pkg_user_ref'      => self::PKG,
                    ],
                ],
                'payload' => $this->buildPayload('1401', 'TANGERANG', [
                    ['product_name'=>'IMPRA WOOD STAIN 0.5L NATURAL TEAK','qty'=>'3','uom'=>'PC',
                     'value_retur'=>830000,'value_retur_ori'=>830000,'kemasan_produk'=>'RUSAK',
                     'kualitas_produk'=>'BAGUS','alasan_retur'=>'KEMASAN RUSAK',
                     'idmsalasan'=>'3','ph'=>self::PH_SAMPLE],
                    ['product_name'=>'IMPRA WOOD STAIN 0.5L DARK WALNUT','qty'=>'2','uom'=>'PC',
                     'value_retur'=>840000,'value_retur_ori'=>840000,'kemasan_produk'=>'RUSAK',
                     'kualitas_produk'=>'BAGUS','alasan_retur'=>'KEMASAN RUSAK',
                     'idmsalasan'=>'3','ph'=>self::PH_SAMPLE],
                ], 2500000),
            ],

            // ── Trial 2: JALUR P3 — nilai 5-15jt, bukan alasan B ─────────
            // BMH sudah approve, sekarang menunggu RRM
            [
                'jalur'        => 'P3 — 5-15jt — BMH✓ → menunggu RRM',
                'doc_ref'      => 'V2-TRIAL-P3-001',
                'source_no'    => 'RT-V2P3001',
                'title'        => '[P3] Retur Cacat Produksi — CV. BERSAMA / SEMARANG — Rp 8.750.000',
                'requester_ref'=> self::BMH_1403,
                'requester_name'=> 'DWI HARYANTO',
                'org_code'     => '1403',
                'org_name'     => 'SEMARANG',
                'amount'       => 8750000,
                'priority'     => 'NORMAL',
                'days_ago'     => 3,
                'source_status'=> 'MENUNGGU PERSETUJUAN RRM',
                'current_node' => 'RRM',
                'completed_nodes' => ['BMH'],
                'context' => [
                    'customer_name'  => 'CV. BERSAMA MAJU',
                    'branch_name'    => 'SEMARANG',
                    'employee_name'  => 'AGUS PRASETYO',
                    'alasan_retur'   => 'CACAT PRODUKSI',
                    'nilai_retur'    => '8750000',
                    'status'         => 'MENUNGGU PERSETUJUAN RRM',
                    '_computed' => [
                        'total_nilai_retur' => 8750000,
                        'idmsalasan_list'   => [7],  // cacat produksi - bukan P6/P7
                        'bmh_user_ref'      => self::BMH_1403,
                        'bmh_user_refs'     => [self::BMH_1403, '11240433'],
                        'rrm_user_ref'      => self::RRM_1403,
                        'nrm_user_ref'      => self::NRM,
                        'ceo_user_ref'      => self::CEO,
                        'pkg_user_ref'      => self::PKG,
                    ],
                ],
                'payload' => $this->buildPayload('1403', 'SEMARANG', [
                    ['product_name'=>'DULUX WEATHERSHIELD 5KG PUTIH','qty'=>'5','uom'=>'PC',
                     'value_retur'=>1750000,'value_retur_ori'=>1750000,'kemasan_produk'=>'BAIK',
                     'kualitas_produk'=>'CACAT','alasan_retur'=>'CACAT PRODUKSI',
                     'idmsalasan'=>'7','ph'=>self::PH_SAMPLE],
                    ['product_name'=>'DULUX WEATHERSHIELD 1KG PUTIH','qty'=>'10','uom'=>'PC',
                     'value_retur'=>700000,'value_retur_ori'=>700000,'kemasan_produk'=>'BAIK',
                     'kualitas_produk'=>'CACAT','alasan_retur'=>'CACAT PRODUKSI',
                     'idmsalasan'=>'7','ph'=>self::PH_SAMPLE],
                ], 8750000),
            ],

            // ── Trial 3: JALUR P6 — idmsalasan 61 (Produk Rusak) ─────────
            // Baru masuk, menunggu BMH
            // Jalur: BMH → RRM → NRM → PMM → PD → CEO
            [
                'jalur'        => 'P6 — Produk Rusak (idmsalasan 61) — BMH menunggu',
                'doc_ref'      => 'V2-TRIAL-P6-001',
                'source_no'    => 'RT-V2P6001',
                'title'        => '[P6] Retur Produk Rusak — TB. SEJAHTERA / TANGERANG — Rp 133.650',
                'requester_ref'=> '11110247',
                'requester_name'=> 'SUNARDI',
                'org_code'     => '1401',
                'org_name'     => 'TANGERANG',
                'amount'       => 133650,
                'priority'     => 'HIGH',
                'days_ago'     => 0,
                'source_status'=> 'MENUNGGU PERSETUJUAN BMH',
                'current_node' => 'BMH',
                'completed_nodes' => [],
                'context' => [
                    'customer_name'  => 'TB. SEJAHTERA ABADI',
                    'branch_name'    => 'TANGERANG',
                    'employee_name'  => 'SUNARDI',
                    'alasan_retur'   => 'PRODUK RUSAK',
                    'nilai_retur'    => '133650',
                    'status'         => 'MENUNGGU PERSETUJUAN BMH',
                    '_computed' => [
                        'total_nilai_retur' => 133650,
                        'idmsalasan_list'   => [61, 68],  // Produk Rusak + Produk Bermasalah → P6
                        'bmh_user_ref'      => self::BMH_1401,
                        'bmh_user_refs'     => [self::BMH_1401, '11080038', '11130010'],
                        'rrm_user_ref'      => self::RRM_1401,
                        'nrm_user_ref'      => self::NRM,
                        'pmm_user_ref'      => $pmmRef,
                        'pd_user_ref'       => $pdRef,
                        'ceo_user_ref'      => self::CEO,
                        'pkg_user_ref'      => self::PKG,
                    ],
                ],
                'payload' => $this->buildPayload('1401', 'TANGERANG', [
                    ['product_name'=>'PROPAN PWS-633 BASE SATIN-1L','qty'=>'1','uom'=>'PC',
                     'value_retur'=>66825,'value_retur_ori'=>66825,'kemasan_produk'=>'RUSAK',
                     'kualitas_produk'=>'RUSAK','alasan_retur'=>'PRODUK RUSAK',
                     'idmsalasan'=>'61','ph'=>self::PH_SAMPLE,
                     'detail_kemasan'=>' - Bocor','detail_kualitas'=>' - Gel/Beku/Keras'],
                    ['product_name'=>'PROPAN PWS-631 BASE SATIN-1L','qty'=>'1','uom'=>'PC',
                     'value_retur'=>66825,'value_retur_ori'=>66825,'kemasan_produk'=>'RUSAK',
                     'kualitas_produk'=>'PRODUK MASALAH','alasan_retur'=>'PRODUK BERMASALAH',
                     'idmsalasan'=>'68','ph'=>self::PH_SAMPLE,
                     'detail_kemasan'=>' - Bocor','detail_kualitas'=>' - Berbau Busuk'],
                ], 133650),
            ],

            // ── Trial 4: JALUR P7 — idmsalasan 33 (Label Rusak) ──────────
            // Baru masuk, menunggu BMH
            // Jalur: BMH → RRM → NRM → PKG → CEO
            [
                'jalur'        => 'P7 — Label Rusak (idmsalasan 33) — BMH menunggu',
                'doc_ref'      => 'V2-TRIAL-P7-001',
                'source_no'    => 'RT-V2P7001',
                'title'        => '[P7] Retur Label Rusak — PD. MAJU TERUS / SEMARANG — Rp 58.378',
                'requester_ref'=> self::BMH_1403,
                'requester_name'=> 'DWI HARYANTO',
                'org_code'     => '1403',
                'org_name'     => 'SEMARANG',
                'amount'       => 58378,
                'priority'     => 'NORMAL',
                'days_ago'     => 0,
                'source_status'=> 'MENUNGGU PERSETUJUAN BMH',
                'current_node' => 'BMH',
                'completed_nodes' => [],
                'context' => [
                    'customer_name'  => 'PD. MAJU TERUS',
                    'branch_name'    => 'SEMARANG',
                    'employee_name'  => 'WAHYU SETIAWAN',
                    'alasan_retur'   => 'LABEL RUSAK',
                    'nilai_retur'    => '58378',
                    'status'         => 'MENUNGGU PERSETUJUAN BMH',
                    '_computed' => [
                        'total_nilai_retur' => 58378,
                        'idmsalasan_list'   => [33],  // Label Rusak → P7
                        'bmh_user_ref'      => self::BMH_1403,
                        'bmh_user_refs'     => [self::BMH_1403, '11240433'],
                        'rrm_user_ref'      => self::RRM_1403,
                        'nrm_user_ref'      => self::NRM,
                        'ceo_user_ref'      => self::CEO,
                        'pkg_user_ref'      => self::PKG,
                    ],
                ],
                'payload' => $this->buildPayload('1403', 'SEMARANG', [
                    ['product_name'=>'PROPAN PWS-631 BASE SATIN-1L','qty'=>'1','uom'=>'PC',
                     'value_retur'=>58378,'value_retur_ori'=>58378,'kemasan_produk'=>'BAIK',
                     'kualitas_produk'=>'BAGUS','alasan_retur'=>'LABEL RUSAK',
                     'idmsalasan'=>'33','ph'=>self::PH_SAMPLE,
                     'detail_kemasan'=>' - Label Rusak'],
                ], 58378),
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildPayload(string $branchId, string $branchName, array $details, float $totalRetur): array
    {
        return [
            'header' => [[
                'tipe_tagihan'    => 'GANTI BARANG/KLAIM',
                'branch_name'     => $branchName,
                'idtblbranch'     => $branchId,
                'customer_name'   => $details[0]['product_name'] ?? '-',
                'nilai_retur'     => (string) $totalRetur,
                'jenis_product'   => 'Non Trading',
                'status'          => 'MENUNGGU PERSETUJUAN BMH',
                'create_time'     => now()->toDateTimeString(),
            ]],
            'detail'  => $details,
            'billing' => ['period' => '2023-02', 'budget' => '133117000'],
            'retur'   => ['total_retur' => (string) $totalRetur],
        ];
    }

    private function resolveUsers(): array
    {
        $refs = [
            self::BMH_1401, self::RRM_1401,
            self::BMH_1403, self::RRM_1403,
            self::NRM, self::CEO, self::PKG,
            '11080038', '11130010', '11240433',
        ];
        $users = TblUser::whereIn('user_ref', $refs)->get()->keyBy('user_ref');
        return $users->toArray();
    }

    private function lookupPmmPd(string $ph4): array
    {
        try {
            $row = DB::select(
                "SELECT produk_manager, pd_manager FROM db_master.ms_product_group WHERE ph4 = ? LIMIT 1",
                [$ph4]
            );
            if (empty($row)) return [null, null];
            return [
                $row[0]->produk_manager ?: null,
                $row[0]->pd_manager     ?: null,
            ];
        } catch (\Throwable $e) {
            $this->command->warn("  PMM/PD lookup gagal: {$e->getMessage()}");
            return [null, null];
        }
    }

    private function createActiveTask($req, $instance, $stepNode, array $t, array $users): void
    {
        // Tentukan siapa assignee berdasarkan current node
        $assigneeRef = match($stepNode->node_code) {
            'BMH' => $t['context']['_computed']['bmh_user_ref'] ?? null,
            'RRM' => $t['context']['_computed']['rrm_user_ref'] ?? null,
            'NRM' => self::NRM,
            'CEO' => self::CEO,
            'PKG' => self::PKG,
            default => null,
        };

        $assigneeUser = $assigneeRef
            ? TblUser::where('user_ref', $assigneeRef)->first()
            : null;

        $taskNo = 'TSK-' . $stepNode->node_code . '-' . $req->idtblapproval_request;
        $taskNo = TblTask::where('task_no', $taskNo)->exists()
            ? $taskNo . '-' . now()->format('His')
            : $taskNo;

        $task = TblTask::create([
            'idtblprocess_instance' => $instance->idtblprocess_instance,
            'idtblapproval_request' => $req->idtblapproval_request,
            'idtblflow_step'        => $stepNode->idtblflow_step,
            'task_no'               => $taskNo,
            'task_status'           => 'OPEN',
            'idtbluser_assigned'    => $assigneeUser?->idtbluser,
            'due_at'                => now()->addHours($stepNode->sla_hours ?? 48),
            'created_at'            => now(),
        ]);

        // Buat candidates: semua BMH di cabang tersebut (untuk node BMH)
        if ($stepNode->node_code === 'BMH') {
            $branchId = $t['context']['_computed']['bmh_user_refs'] ?? [$assigneeRef];
            foreach ((array)$branchId as $idx => $npk) {
                $u = TblUser::where('user_ref', $npk)->first();
                if ($u) {
                    TblTaskCandidate::firstOrCreate(
                        ['task_id' => $task->idtbltask, 'idtbluser' => $u->idtbluser],
                        ['candidate_source' => 'DIRECT', 'priority_no' => $idx + 1, 'is_active' => 1]
                    );
                }
            }
        } elseif ($assigneeUser) {
            TblTaskCandidate::firstOrCreate(
                ['task_id' => $task->idtbltask, 'idtbluser' => $assigneeUser->idtbluser],
                ['candidate_source' => 'DIRECT', 'priority_no' => 1, 'is_active' => 1]
            );
        }
    }

    private function createLogs($req, $instance, $flowVersion, $nodes, array $t, Carbon $submittedAt, array $users): void
    {
        // Log START
        $this->log($req, $instance, $flowVersion, $nodes['START'], 'ENTER_NODE', 'START',
            'SUBMIT', "Dokumen masuk dari SFA. Requester: {$t['requester_name']} ({$t['org_name']})",
            $t['requester_ref'], $submittedAt);
        $this->log($req, $instance, $flowVersion, $nodes['START'], 'EXIT_NODE', 'START',
            'SUBMIT', 'Engine meneruskan ke DECISION_JALUR.',
            'SYSTEM', $submittedAt->copy()->addSeconds(2));

        // Log DECISION_JALUR
        $this->log($req, $instance, $flowVersion, $nodes['DECISION_JALUR'], 'ENTER_NODE', 'DECISION',
            null, "Mengevaluasi kondisi. total_nilai_retur={$t['context']['_computed']['total_nilai_retur']}, idmsalasan=" . implode(',', $t['context']['_computed']['idmsalasan_list']),
            'SYSTEM', $submittedAt->copy()->addSeconds(3));
        $this->log($req, $instance, $flowVersion, $nodes['DECISION_JALUR'], 'EXIT_NODE', 'DECISION',
            'AUTO_APPROVE', "Jalur terpilih: {$t['jalur']}. Dokumen diteruskan ke BMH.",
            'SYSTEM', $submittedAt->copy()->addSeconds(4));

        // Log node yang sudah selesai (completed_nodes)
        $offset = 5;
        foreach ($t['completed_nodes'] as $nodeCode) {
            if (! isset($nodes[$nodeCode])) continue;
            $approverRef = $this->getApproverRef($nodeCode, $t);

            $this->log($req, $instance, $flowVersion, $nodes[$nodeCode], 'TASK_CREATED', 'APPROVAL',
                null, "Task dibuat untuk {$nodes[$nodeCode]->step_name}. Assignee: {$approverRef}",
                'SYSTEM', $submittedAt->copy()->addSeconds($offset++));
            $this->log($req, $instance, $flowVersion, $nodes[$nodeCode], 'EXIT_NODE', 'APPROVAL',
                'APPROVE', "{$nodes[$nodeCode]->step_name} menyetujui dokumen.",
                $approverRef, $submittedAt->copy()->addHours($offset++));
        }

        // Log ENTER current node
        $curRef = $this->getApproverRef($t['current_node'], $t);
        $this->log($req, $instance, $flowVersion, $nodes[$t['current_node']], 'TASK_CREATED', 'APPROVAL',
            null, "Task dibuat untuk {$nodes[$t['current_node']]->step_name}. Menunggu persetujuan.",
            'SYSTEM', $submittedAt->copy()->addSeconds($offset++));
        $this->log($req, $instance, $flowVersion, $nodes[$t['current_node']], 'ENTER_NODE', 'APPROVAL',
            null, "Menunggu persetujuan dari {$nodes[$t['current_node']]->step_name}.",
            'SYSTEM', $submittedAt->copy()->addSeconds($offset));
    }

    private function getApproverRef(string $nodeCode, array $t): string
    {
        return match($nodeCode) {
            'BMH' => $t['context']['_computed']['bmh_user_ref'] ?? 'BMH',
            'RRM' => $t['context']['_computed']['rrm_user_ref'] ?? 'RRM',
            'NRM' => self::NRM,
            'CEO' => self::CEO,
            'PKG' => self::PKG,
            default => 'SYSTEM',
        };
    }

    private function log($req, $instance, $fv, $step, string $event, string $nodeType,
                         ?string $action, string $msg, string $actor, Carbon $at): void
    {
        TblProcessRouteLog::create([
            'idtblapproval_request'  => $req->idtblapproval_request,
            'idtblprocess_instance'  => $instance->idtblprocess_instance,
            'idtblflow_step'         => $step->idtblflow_step,
            'route_event'            => $event,
            'node_type'              => $nodeType,
            'action_code'            => $action,
            'condition_result'       => null,
            'message'                => $msg,
            'created_by'             => $actor,
            'created_at'             => $at,
        ]);
    }

    // ── Print login guide ─────────────────────────────────────────────────

    private function printLoginGuide(array $trials, ?string $pmmRef, ?string $pdRef): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  ✅  Selesai! Panduan Login & Approval:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Password semua user: Propan@{NPK}');
        $this->command->info('  Contoh: Propan@11110247');
        $this->command->info('');

        $this->command->info('  ┌─ Trial 1: V2-TRIAL-P4-001 ─ JALUR P4 (≤5jt) ─────────────┐');
        $this->command->info('  │  Alur: BMH → SELESAI                                        │');
        $this->command->info('  │  Step 1 (saat ini): Login sebagai salah satu BMH Tangerang: │');
        $this->command->info('  │    NPK 11110247 - SLAMET SANTOSO    (pw: Propan@11110247)   │');
        $this->command->info('  │    NPK 11080038 - ANDREY LUNARDY    (pw: Propan@11080038)   │');
        $this->command->info('  │    NPK 11130010 - ZAINAL             (pw: Propan@11130010)   │');
        $this->command->info('  │  → Approve → Dokumen langsung APPROVED (selesai)             │');
        $this->command->info('  └─────────────────────────────────────────────────────────────┘');
        $this->command->info('');

        $this->command->info('  ┌─ Trial 2: V2-TRIAL-P3-001 ─ JALUR P3 (5-15jt) ───────────┐');
        $this->command->info('  │  Alur: BMH✓ → RRM → SELESAI                               │');
        $this->command->info('  │  BMH sudah approve (DWI HARYANTO)                          │');
        $this->command->info('  │  Step saat ini: Login sebagai RRM Semarang:                │');
        $this->command->info('  │    NPK 11020031 - AGUS WIDJAJA       (pw: Propan@11020031) │');
        $this->command->info('  │  → Approve → Dokumen APPROVED (selesai)                   │');
        $this->command->info('  └─────────────────────────────────────────────────────────────┘');
        $this->command->info('');

        $this->command->info('  ┌─ Trial 3: V2-TRIAL-P6-001 ─ JALUR P6 (Produk Rusak) ─────┐');
        $this->command->info('  │  Alur: BMH→RRM→NRM→PMM→PD→CEO                            │');
        $this->command->info('  │  Step 1: BMH Tangerang (pilih salah satu):                │');
        $this->command->info('  │    NPK 11110247 - SLAMET SANTOSO    (pw: Propan@11110247) │');
        $this->command->info('  │    NPK 11080038 - ANDREY LUNARDY    (pw: Propan@11080038) │');
        $this->command->info('  │  Step 2: RRM → NPK 11030021 - MOH. CARNO ADINATA          │');
        $this->command->info('  │  Step 3: NRM → NPK 11990056 - JULIUS KURATA               │');
        if ($pmmRef) {
            $this->command->info("  │  Step 4: PMM → NPK {$pmmRef}                       │");
        } else {
            $this->command->info('  │  Step 4: PMM → (cek ms_product_group PH=BH0702)         │');
        }
        if ($pdRef) {
            $this->command->info("  │  Step 5: PD  → NPK {$pdRef}                       │");
        } else {
            $this->command->info('  │  Step 5: PD  → (cek ms_product_group PH=BH0702)         │');
        }
        $this->command->info('  │  Step 6: CEO → NPK 1030018 - KRIS RIANTO ADIDARMA        │');
        $this->command->info('  │           (pw: Propan@1030018)                             │');
        $this->command->info('  └─────────────────────────────────────────────────────────────┘');
        $this->command->info('');

        $this->command->info('  ┌─ Trial 4: V2-TRIAL-P7-001 ─ JALUR P7 (Label Rusak) ──────┐');
        $this->command->info('  │  Alur: BMH→RRM→NRM→PKG(Packaging)→CEO                   │');
        $this->command->info('  │  Step 1: BMH Semarang (pilih salah satu):                 │');
        $this->command->info('  │    NPK 11150116 - DWI HARYANTO      (pw: Propan@11150116) │');
        $this->command->info('  │    NPK 11240433 - BERNADUS TRAJU    (pw: Propan@11240433) │');
        $this->command->info('  │  Step 2: RRM → NPK 11020031 - AGUS WIDJAJA                │');
        $this->command->info('  │  Step 3: NRM → NPK 11990056 - JULIUS KURATA               │');
        $this->command->info('  │  Step 4: PKG → NPK 11130476 - HENDRI GUNAWAN              │');
        $this->command->info('  │           (pw: Propan@11130476) — dicari via JT0286        │');
        $this->command->info('  │  Step 5: CEO → NPK 1030018  - KRIS RIANTO ADIDARMA       │');
        $this->command->info('  │           (pw: Propan@1030018)                             │');
        $this->command->info('  └─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('  URL Inbox: /approval_center/public/inbox');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
