<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TblApprovalRequest;
use App\Models\TblProcessInstance;
use App\Models\TblFlowStep;
use App\Models\TblFlowVersion;
use App\Models\TblTask;
use App\Models\TblTaskCandidate;
use App\Models\TblSourceApp;
use App\Models\TblDocumentType;
use App\Models\TblUser;
use App\Models\TblRoutingRule;
use App\Models\TblStepAssigneeRule;
use App\Models\TblProcessRouteLog;

/**
 * SfaReturTrialSeeder
 *
 * Mensimulasikan 3 approval request SFA Retur dengan skenario berbeda:
 *   1. waiting_bmh  — baru masuk, menunggu approval step pertama
 *   2. waiting_rrm  — step pertama sudah approve, menunggu step kedua
 *   3. approved     — semua step selesai, APPROVED
 *
 * Jalankan: php artisan db:seed --class=SfaReturTrialSeeder
 */
class SfaReturTrialSeeder extends Seeder
{
    private array $samples = [
        [
            'doc_ref'        => 'SFA-RETUR-2025-001',
            'source_no'      => 'RT-2263202511140932101',
            'title'          => 'Retur Barang — TC. BEN JOYO / MOCHAMAD SUMARDI — Semarang',
            'requester_ref'  => '11170317',
            'requester_name' => 'NIKEN INDAH SRI REJEKI',
            'org_code'       => '1403',
            'org_name'       => 'SEMARANG',
            'amount'         => 85211.00,
            'priority'       => 'NORMAL',
            'scenario'       => 'waiting_bmh',
            'context' => [
                'tipe_tagihan'     => 'GANTI BARANG/KLAIM',
                'customer_name'    => 'TC. BEN JOYO/SOEPENGNO B',
                'employee_name'    => 'MOCHAMAD SUMARDI',
                'branch_name'      => 'SEMARANG',
                'alasan_retur'     => 'KEMASAN RUSAK',
                'nilai_retur'      => '12141300.00',
                'nilai_omset'      => '699707000.00',
                'nilai_persen'     => '1.74',
                'jenis_product'    => 'Non Trading',
                'status'           => 'MENUNGGU PERSETUJUAN BMH',
                'create_time'      => '2025-11-14 09:33:47',
                'idtbltakingorder' => '2263202511140932101',
            ],
            'payload' => [
                'header' => [[
                    'tipe_tagihan'  => 'GANTI BARANG/KLAIM',
                    'customer_name' => 'TC. BEN JOYO/SOEPENGNO B',
                    'shipto'        => '1200041403',
                    'branch_name'   => 'SEMARANG',
                    'employee_name' => 'MOCHAMAD SUMARDI',
                    'alasan_retur'  => 'KEMASAN RUSAK',
                    'nilai_retur'   => '12141300.00',
                    'nilai_omset'   => '699707000.00',
                    'nilai_persen'  => '1.74',
                    'jenis_product' => 'Non Trading',
                    'status'        => 'MENUNGGU PERSETUJUAN BMH',
                    'create_time'   => '2025-11-14 09:33:47',
                    'budget_from'   => '01 Jan 2023',
                    'budget_to'     => '13 Nov 2025',
                    'idtbltakingorder' => '2263202511140932101',
                ]],
                'detail' => [[
                    'product_name'        => 'IMPRA WS-162 B CANDY YELLOW-1L',
                    'qty'                 => '1',
                    'uom'                 => 'PC',
                    'value_retur'         => 85211,
                    'value_retur_ori'     => 85211,
                    'value_potong_budget' => 76767,
                    'kemasan_produk'      => 'RUSAK',
                    'kualitas_produk'     => 'BAGUS',
                    'isi_produk'          => '100',
                    'alasan_retur'        => 'KEMASAN RUSAK',
                    'disc'                => '7.50+6+',
                    'mts_mto'             => 'MTS',
                    'rilis_date'          => '31/01/2023',
                    'real_batch'          => 'WV5X5HUVQE',
                    'no_batch'            => '23010939',
                    'pricelist'           => '98000',
                    'detail_kemasan'      => ' - Penyok',
                    'foto_all'            => '1200041403_371_093350_foto.jpg',
                ]],
                'history' => [[
                    'status'        => 'DOKUMEN DIBUAT',
                    'xcreated_date' => '14/11/25 09:33',
                    'employee_name' => 'NIKEN INDAH SRI REJEKI',
                    'jobtitlename'  => 'RETAIL SALES REPRESENTATIVE',
                    'notes'         => '',
                ]],
                'billing' => [
                    'period'        => '2023-02',
                    'total_billing' => '33279321255.00',
                    'budget'        => '133117000',
                ],
                'retur'            => ['total_retur' => '1005515283'],
                'retur_skrng'      => ['total_retur' => '0'],
                'detail_budget_retur' => [[
                    'total'   => '98000',
                    'percent' => '1.000000',
                    'disc'    => '12789',
                    'all'     => '85211',
                ]],
            ],
        ],
        [
            'doc_ref'        => 'SFA-RETUR-2025-002',
            'source_no'      => 'RT-2263202511152341802',
            'title'          => 'Retur Barang — CV. MAJU JAYA / BUDI SANTOSO — Jakarta Barat',
            'requester_ref'  => '11180421',
            'requester_name' => 'SARI DEWI',
            'org_code'       => '1101',
            'org_name'       => 'JAKARTA BARAT',
            'amount'         => 156000.00,
            'priority'       => 'HIGH',
            'scenario'       => 'waiting_rrm',
            'context' => [
                'tipe_tagihan'  => 'GANTI BARANG/KLAIM',
                'customer_name' => 'CV. MAJU JAYA',
                'employee_name' => 'BUDI SANTOSO',
                'branch_name'   => 'JAKARTA BARAT',
                'alasan_retur'  => 'PRODUK CACAT PRODUKSI',
                'nilai_retur'   => '18500000.00',
                'nilai_omset'   => '1250000000.00',
                'nilai_persen'  => '1.48',
                'jenis_product' => 'Trading',
                'status'        => 'MENUNGGU PERSETUJUAN RRM',
                'create_time'   => '2025-11-15 14:22:10',
            ],
            'payload' => [
                'header' => [[
                    'tipe_tagihan'  => 'GANTI BARANG/KLAIM',
                    'customer_name' => 'CV. MAJU JAYA',
                    'shipto'        => '1100021801',
                    'branch_name'   => 'JAKARTA BARAT',
                    'employee_name' => 'BUDI SANTOSO',
                    'alasan_retur'  => 'PRODUK CACAT PRODUKSI',
                    'nilai_retur'   => '18500000.00',
                    'nilai_omset'   => '1250000000.00',
                    'jenis_product' => 'Trading',
                    'status'        => 'BMH SUDAH APPROVE — MENUNGGU RRM',
                    'create_time'   => '2025-11-15 14:22:10',
                ]],
                'detail' => [
                    [
                        'product_name'    => 'DULUX WEATHERSHIELD 5KG PUTIH',
                        'qty'             => '2',
                        'uom'             => 'PC',
                        'value_retur'     => 78000,
                        'kemasan_produk'  => 'BAIK',
                        'kualitas_produk' => 'CACAT',
                        'alasan_retur'    => 'CACAT PRODUKSI',
                        'mts_mto'         => 'MTS',
                        'no_batch'        => '23080512',
                    ],
                    [
                        'product_name'    => 'DULUX WEATHERSHIELD 1KG PUTIH',
                        'qty'             => '4',
                        'uom'             => 'PC',
                        'value_retur'     => 78000,
                        'kemasan_produk'  => 'BAIK',
                        'kualitas_produk' => 'CACAT',
                        'alasan_retur'    => 'CACAT PRODUKSI',
                        'mts_mto'         => 'MTS',
                        'no_batch'        => '23080513',
                    ],
                ],
                'billing' => ['period' => '2023-08', 'budget' => '250000000'],
            ],
        ],
        [
            'doc_ref'        => 'SFA-RETUR-2025-003',
            'source_no'      => 'RT-2263202510291054501',
            'title'          => 'Retur Barang — UD. SINAR TERANG / AGUS WIBOWO — Surabaya',
            'requester_ref'  => '11160298',
            'requester_name' => 'RATNA KUSUMA',
            'org_code'       => '1502',
            'org_name'       => 'SURABAYA',
            'amount'         => 45600.00,
            'priority'       => 'NORMAL',
            'scenario'       => 'approved',
            'context' => [
                'tipe_tagihan'  => 'GANTI BARANG/KLAIM',
                'customer_name' => 'UD. SINAR TERANG',
                'employee_name' => 'AGUS WIBOWO',
                'branch_name'   => 'SURABAYA',
                'alasan_retur'  => 'KEMASAN RUSAK',
                'nilai_retur'   => '5600000.00',
                'nilai_omset'   => '890000000.00',
                'nilai_persen'  => '0.63',
                'jenis_product' => 'Non Trading',
                'status'        => 'APPROVED',
                'create_time'   => '2025-10-29 10:54:22',
            ],
            'payload' => [
                'header' => [[
                    'tipe_tagihan'  => 'GANTI BARANG/KLAIM',
                    'customer_name' => 'UD. SINAR TERANG',
                    'branch_name'   => 'SURABAYA',
                    'employee_name' => 'AGUS WIBOWO',
                    'alasan_retur'  => 'KEMASAN RUSAK',
                    'nilai_retur'   => '5600000.00',
                    'nilai_omset'   => '890000000.00',
                    'jenis_product' => 'Non Trading',
                    'status'        => 'APPROVED',
                    'create_time'   => '2025-10-29 10:54:22',
                ]],
                'detail' => [[
                    'product_name'    => 'IMPRA WOOD STAIN 1L NATURAL TEAK',
                    'qty'             => '3',
                    'uom'             => 'PC',
                    'value_retur'     => 45600,
                    'kemasan_produk'  => 'RUSAK',
                    'kualitas_produk' => 'BAGUS',
                    'alasan_retur'    => 'KEMASAN RUSAK',
                    'mts_mto'         => 'MTS',
                ]],
            ],
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  SFA Retur Trial Seeder');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // 1. Resolve master data ─────────────────────────────────────────
        $sourceApp = TblSourceApp::where('app_code', 'SFA')->first()
            ?? TblSourceApp::first();
        if (! $sourceApp) {
            $this->command->error('Tidak ada Source App! Buat dulu di Master → Source App (app_code: SFA).');
            return;
        }
        $this->command->line("  Source App : {$sourceApp->app_code} (id={$sourceApp->idtblsource_app})");

        $docType = TblDocumentType::where('doc_code', 'SFA_R1')
            ->where('idtblsource_app', $sourceApp->idtblsource_app)->first()
            ?? TblDocumentType::where('idtblsource_app', $sourceApp->idtblsource_app)->first();
        if (! $docType) {
            $this->command->error('Document Type SFA_R1 tidak ditemukan. Buat dulu di Master → Document Type.');
            return;
        }
        $this->command->line("  Doc Type   : {$docType->doc_code} (id={$docType->idtbldocument_type})");

        $flowVersion = $this->findFlowVersion($sourceApp->idtblsource_app, $docType->idtbldocument_type);
        if (! $flowVersion) {
            $this->command->error('Tidak ada Flow Version ACTIVE! Deploy flow dulu dari menu Workflow → Visual Builder → Deploy.');
            return;
        }
        $this->command->line("  Flow       : {$flowVersion->version_name} v{$flowVersion->version_no} (id={$flowVersion->idtblflow_version})");

        // 2. Load nodes ──────────────────────────────────────────────────
        $allNodes = TblFlowStep::where('idtblflow_version', $flowVersion->idtblflow_version)
            ->orderBy('step_order')->get();

        $startNode     = $allNodes->firstWhere('step_type', 'START');
        $endNode       = $allNodes->firstWhere('step_type', 'END');
        $approvalNodes = $allNodes->where('step_type', 'APPROVAL')->values();

        if (! $startNode || ! $endNode) {
            $this->command->error('Flow tidak lengkap — tidak ada node START atau END.');
            return;
        }
        $this->command->line("  Nodes      : " . $allNodes->count() . " total, " .
            $approvalNodes->count() . " APPROVAL (" . $approvalNodes->pluck('node_code')->implode(' → ') . ")");

        // 3. Resolve approvers ───────────────────────────────────────────
        $approvers = $this->findApprovers($approvalNodes);
        if ($approvers->isEmpty()) {
            $this->command->warn('  Approver   : Tidak ditemukan — task dibuat tanpa assignee.');
        } else {
            $this->command->line("  Approvers  : " . $approvers->pluck('user_ref')->implode(', '));
        }

        // 4. Buat setiap request ─────────────────────────────────────────
        $this->command->info('');
        $created = 0;

        foreach ($this->samples as $s) {
            $this->command->info("  ▶ {$s['doc_ref']} [{$s['scenario']}]");

            $exists = TblApprovalRequest::where('source_request_id', $s['doc_ref'])
                ->where('idtblsource_app', $sourceApp->idtblsource_app)->exists();
            if ($exists) {
                $this->command->warn('    Skip — sudah ada.');
                continue;
            }

            DB::transaction(function () use ($s, $sourceApp, $docType, $flowVersion,
                                             $startNode, $endNode, $approvalNodes, $approvers) {
                $now = Carbon::now();

                // ── Waktu simulasi ──────────────────────────────────
                $submittedAt = $now->copy()->subDays(rand(3, 7));

                // ── Tentukan status dan current step ────────────────
                [$reqStatus, $instStatus, $currentStep] = match($s['scenario']) {
                    'approved'    => ['APPROVED',    'COMPLETED', $endNode],
                    'waiting_rrm' => ['IN_PROGRESS', 'RUNNING',   $approvalNodes->get(1) ?? $approvalNodes->first()],
                    default       => ['IN_PROGRESS', 'RUNNING',   $approvalNodes->first()],
                };

                // ── tblapproval_request ─────────────────────────────
                $req = TblApprovalRequest::create([
                    'idtblsource_app'            => $sourceApp->idtblsource_app,
                    'idtbldocument_type'          => $docType->idtbldocument_type,
                    'source_request_id'           => $s['doc_ref'],
                    'source_request_no'           => $s['source_no'],
                    'idempotency_key'             => 'trial_' . $s['doc_ref'],
                    'title'                       => $s['title'],
                    'requester_ref'               => $s['requester_ref'],
                    'requester_name'              => $s['requester_name'],
                    'requester_org_code'          => $s['org_code'],
                    'requester_org_name'          => $s['org_name'],
                    'amount'                      => $s['amount'],
                    'currency_code'               => 'IDR',
                    'priority'                    => $s['priority'],
                    'request_status'              => $reqStatus,
                    'source_status'               => $s['context']['status'],
                    'callback_url'                => 'http://sfa.propan.internal/approval/callback',
                    'context_json'                => $s['context'],
                    'payload_json'                => $s['payload'],
                    'idtblflow_version_selected'  => $flowVersion->idtblflow_version,
                    'idtblflow_step_current'      => $currentStep->idtblflow_step,
                    'submitted_at'                => $submittedAt,
                    'completed_at'                => $reqStatus === 'APPROVED' ? $now->copy()->subHours(rand(2, 12)) : null,
                ]);

                // ── tblprocess_instance ─────────────────────────────
                $instance = TblProcessInstance::create([
                    'idtblapproval_request'  => $req->idtblapproval_request,
                    'idtblflow_version'      => $flowVersion->idtblflow_version,
                    'instance_status'        => $instStatus,
                    'idtblflow_step_current' => $currentStep->idtblflow_step,
                    'started_at'             => $submittedAt,
                    'ended_at'               => $instStatus === 'COMPLETED' ? $req->completed_at : null,
                ]);

                // ── tblprocess_route_log ────────────────────────────
                $this->insertRouteLogs($req, $instance, $flowVersion, $startNode, $approvalNodes, $endNode, $s['scenario'], $submittedAt, $approvers);

                // ── tbltask ─────────────────────────────────────────
                $this->insertTasks($req, $instance, $approvalNodes, $endNode, $s['scenario'], $approvers, $submittedAt);

                $this->command->info("    ✓ Request #{$req->idtblapproval_request} — {$req->request_status}");
            });

            $created++;
        }

        // 5. Summary ─────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("  ✅  Selesai — {$created} request dibuat.");
        $this->command->info('');
        $this->command->info('  Cek di:');
        $this->command->info('  • Inbox       → task OPEN menunggu keputusan Anda');
        $this->command->info('  • Monitoring  → semua 3 request + status');
        $this->command->info('  • Dashboard   → KPI cards terupdate');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function findFlowVersion(int $sourceAppId, int $docTypeId): ?TblFlowVersion
    {
        // Via routing rule
        $rule = TblRoutingRule::where('idtblsource_app', $sourceAppId)
            ->where('idtbldocument_type', $docTypeId)
            ->where('is_active', 1)
            ->orderBy('priority_no')->first();
        if ($rule?->idtblflow_version) {
            $v = TblFlowVersion::find($rule->idtblflow_version);
            if ($v?->status === 'ACTIVE') return $v;
        }

        // Via flow definition
        return \App\Models\TblFlowVersion::whereHas('flowDefinition', function ($q) use ($sourceAppId, $docTypeId) {
            $q->where('idtblsource_app', $sourceAppId)
              ->where('idtbldocument_type', $docTypeId);
        })->where('status', 'ACTIVE')->latest()->first()

        // Fallback: ACTIVE version dari source app manapun
        ?? \App\Models\TblFlowVersion::where('status', 'ACTIVE')->latest()->first();
    }

    private function findApprovers($approvalNodes): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach ($approvalNodes as $node) {
            $rules = TblStepAssigneeRule::where('idtblflow_step', $node->idtblflow_step)
                ->where('is_active', 1)->orderBy('priority_no')->get();
            foreach ($rules as $rule) {
                $user = match($rule->assignee_type) {
                    'USER' => TblUser::where('user_ref', $rule->assignee_value)->first(),
                    'ROLE' => TblUser::whereHas('roles', fn($q) => $q->where('role_code', $rule->assignee_value))->first(),
                    default => null,
                };
                if ($user && ! $result->contains('idtbluser', $user->idtbluser)) {
                    $result->push($user);
                }
            }
        }
        // Fallback: user APPROVER
        if ($result->isEmpty()) {
            $result = TblUser::whereHas('roles', fn($q) => $q->where('role_code', 'APPROVER'))->limit(5)->get();
        }
        return $result;
    }

    private function approversForStep($step, $allApprovers): \Illuminate\Support\Collection
    {
        $rules = TblStepAssigneeRule::where('idtblflow_step', $step->idtblflow_step)
            ->where('is_active', 1)->orderBy('priority_no')->get();
        $result = collect();
        foreach ($rules as $rule) {
            match($rule->assignee_type) {
                'USER' => ($u = TblUser::where('user_ref', $rule->assignee_value)->first()) && $result->push($u),
                'ROLE' => $result = $result->merge(
                    TblUser::whereHas('roles', fn($q) => $q->where('role_code', $rule->assignee_value))->get()
                ),
                default => null,
            };
        }
        return $result->isEmpty() ? $allApprovers->take(1) : $result->unique('idtbluser');
    }

    private function taskNo(string $nodeCode, int $reqId, string $suffix = ''): string
    {
        $base = 'TSK-' . strtoupper(substr($nodeCode, 0, 4)) . '-' . $reqId . ($suffix ? '-' . $suffix : '');
        // Hindari duplikat
        $i = 0;
        $candidate = $base;
        while (TblTask::where('task_no', $candidate)->exists()) {
            $candidate = $base . '-' . (++$i);
        }
        return $candidate;
    }

    private function insertTasks($req, $instance, $approvalNodes, $endNode, string $scenario, $approvers, Carbon $submittedAt): void
    {
        foreach ($approvalNodes as $idx => $node) {
            $stepApprovers = $this->approversForStep($node, $approvers);
            $approver      = $stepApprovers->first();
            $enteredAt     = $submittedAt->copy()->addHours($idx * 8 + 1);

            if ($scenario === 'waiting_bmh' && $idx === 0) {
                // Task OPEN — menunggu approval
                $this->makeOpenTask($req, $instance, $node, $stepApprovers, $enteredAt);
                break;
            }

            if ($scenario === 'waiting_rrm') {
                if ($idx === 0) {
                    // Task sudah APPROVED
                    $this->makeClosedTask($req, $instance, $node, $approver, 'APPROVE', 'APPROVED',
                        'Setuju, barang memang rusak saat diterima.', $enteredAt, $enteredAt->copy()->addHours(5));
                } elseif ($idx === 1) {
                    // Task OPEN
                    $this->makeOpenTask($req, $instance, $node, $stepApprovers, $enteredAt);
                    break;
                }
            }

            if ($scenario === 'approved') {
                $notes = $idx === 0 ? 'Barang rusak terbukti dari foto. Setuju diganti.'
                                    : 'Sudah sesuai prosedur. Disetujui.';
                $this->makeClosedTask($req, $instance, $node, $approver, 'APPROVE', 'APPROVED',
                    $notes, $enteredAt, $enteredAt->copy()->addHours(3));
            }
        }
    }

    private function makeOpenTask($req, $instance, $node, $stepApprovers, Carbon $createdAt): void
    {
        $approver = $stepApprovers->first();
        $task = TblTask::create([
            'idtblprocess_instance' => $instance->idtblprocess_instance,
            'idtblapproval_request' => $req->idtblapproval_request,
            'idtblflow_step'        => $node->idtblflow_step,
            'task_no'               => $this->taskNo($node->node_code, $req->idtblapproval_request),
            'task_status'           => 'OPEN',
            'idtbluser_assigned'    => $approver?->idtbluser,
            'due_at'                => $node->sla_hours
                ? $createdAt->copy()->addHours($node->sla_hours)
                : $createdAt->copy()->addDays(3),
            'created_at'            => $createdAt,
        ]);

        foreach ($stepApprovers->unique('idtbluser') as $i => $u) {
            TblTaskCandidate::firstOrCreate(
                ['task_id' => $task->idtbltask, 'idtbluser' => $u->idtbluser],
                ['candidate_source' => 'DIRECT', 'priority_no' => $i + 1, 'is_active' => 1]
            );
        }
    }

    private function makeClosedTask($req, $instance, $node, $approver, string $decision,
                                    string $taskStatus, string $note,
                                    Carbon $createdAt, Carbon $completedAt): void
    {
        TblTask::create([
            'idtblprocess_instance'  => $instance->idtblprocess_instance,
            'idtblapproval_request'  => $req->idtblapproval_request,
            'idtblflow_step'         => $node->idtblflow_step,
            'task_no'                => $this->taskNo($node->node_code, $req->idtblapproval_request, 'DONE'),
            'task_status'            => $taskStatus,
            'idtbluser_assigned'     => $approver?->idtbluser,
            'idtbluser_claimed_by'   => $approver?->idtbluser,
            'idtbluser_completed_by' => $approver?->idtbluser,
            'decision_code'          => $decision === 'APPROVE' ? 'APPROVE' : 'REJECT',
            'decision_note'          => $note,
            'due_at'                 => $node->sla_hours
                ? $createdAt->copy()->addHours($node->sla_hours)
                : $createdAt->copy()->addDays(3),
            'claimed_at'             => $createdAt->copy()->addMinutes(30),
            'completed_at'           => $completedAt,
            'created_at'             => $createdAt,
        ]);
    }

    private function insertRouteLogs($req, $instance, $flowVersion, $startNode,
                                     $approvalNodes, $endNode, string $scenario,
                                     Carbon $submittedAt, $approvers): void
    {
        // START — ENTER
        TblProcessRouteLog::create([
            'idtblapproval_request'  => $req->idtblapproval_request,
            'idtblprocess_instance'  => $instance->idtblprocess_instance,
            'idtblflow_step'         => $startNode->idtblflow_step,
            'route_event'            => 'ENTER_NODE',
            'node_type'              => 'START',
            'action_code'            => 'SUBMIT',
            'condition_result'       => null,
            'message'                => "Dokumen masuk dari SFA. Requester: {$req->requester_name} ({$req->requester_org_name})",
            'created_by'             => $req->requester_ref,
            'created_at'             => $submittedAt,
        ]);
        TblProcessRouteLog::create([
            'idtblapproval_request'  => $req->idtblapproval_request,
            'idtblprocess_instance'  => $instance->idtblprocess_instance,
            'idtblflow_step'         => $startNode->idtblflow_step,
            'route_event'            => 'EXIT_NODE',
            'node_type'              => 'START',
            'action_code'            => 'SUBMIT',
            'message'                => 'Start node selesai, meneruskan ke node berikutnya.',
            'created_by'             => 'SYSTEM',
            'created_at'             => $submittedAt->copy()->addSeconds(2),
        ]);

        // APPROVAL nodes
        foreach ($approvalNodes as $idx => $node) {
            $enterAt  = $submittedAt->copy()->addHours($idx * 8 + 1);
            $approver = $approvers->get($idx) ?? $approvers->first();

            // TASK_CREATED log
            TblProcessRouteLog::create([
                'idtblapproval_request'  => $req->idtblapproval_request,
                'idtblprocess_instance'  => $instance->idtblprocess_instance,
                'idtblflow_step'         => $node->idtblflow_step,
                'route_event'            => 'TASK_CREATED',
                'node_type'              => 'APPROVAL',
                'action_code'            => null,
                'message'                => "Task dibuat untuk node {$node->node_code} — {$node->step_name}. Assignee: " . ($approver?->full_name ?? 'N/A'),
                'created_by'             => 'SYSTEM',
                'created_at'             => $enterAt,
            ]);

            // Untuk skenario waiting_bmh: hanya log masuk di node 1
            if ($scenario === 'waiting_bmh' && $idx === 0) {
                TblProcessRouteLog::create([
                    'idtblapproval_request'  => $req->idtblapproval_request,
                    'idtblprocess_instance'  => $instance->idtblprocess_instance,
                    'idtblflow_step'         => $node->idtblflow_step,
                    'route_event'            => 'ENTER_NODE',
                    'node_type'              => 'APPROVAL',
                    'message'                => "Menunggu persetujuan {$node->step_name}.",
                    'created_by'             => 'SYSTEM',
                    'created_at'             => $enterAt->copy()->addSeconds(1),
                ]);
                break;
            }

            // Untuk skenario waiting_rrm
            if ($scenario === 'waiting_rrm') {
                if ($idx === 0) {
                    // BMH sudah approve
                    TblProcessRouteLog::create([
                        'idtblapproval_request'  => $req->idtblapproval_request,
                        'idtblprocess_instance'  => $instance->idtblprocess_instance,
                        'idtblflow_step'         => $node->idtblflow_step,
                        'route_event'            => 'EXIT_NODE',
                        'node_type'              => 'APPROVAL',
                        'action_code'            => 'APPROVE',
                        'condition_result'       => 1,
                        'message'                => "{$node->step_name} menyetujui. Catatan: Setuju, barang rusak terbukti.",
                        'created_by'             => $approver?->user_ref ?? 'APPROVER',
                        'created_at'             => $enterAt->copy()->addHours(4),
                    ]);
                } elseif ($idx === 1) {
                    TblProcessRouteLog::create([
                        'idtblapproval_request'  => $req->idtblapproval_request,
                        'idtblprocess_instance'  => $instance->idtblprocess_instance,
                        'idtblflow_step'         => $node->idtblflow_step,
                        'route_event'            => 'ENTER_NODE',
                        'node_type'              => 'APPROVAL',
                        'message'                => "Menunggu persetujuan {$node->step_name}.",
                        'created_by'             => 'SYSTEM',
                        'created_at'             => $enterAt,
                    ]);
                    break;
                }
            }

            // Skenario approved
            if ($scenario === 'approved') {
                $note = $idx === 0 ? 'Barang rusak terbukti dari foto.' : 'Sudah sesuai prosedur.';
                TblProcessRouteLog::create([
                    'idtblapproval_request'  => $req->idtblapproval_request,
                    'idtblprocess_instance'  => $instance->idtblprocess_instance,
                    'idtblflow_step'         => $node->idtblflow_step,
                    'route_event'            => 'EXIT_NODE',
                    'node_type'              => 'APPROVAL',
                    'action_code'            => 'APPROVE',
                    'condition_result'       => 1,
                    'message'                => "{$node->step_name} menyetujui. Catatan: {$note}",
                    'created_by'             => $approver?->user_ref ?? 'APPROVER',
                    'created_at'             => $enterAt->copy()->addHours(3),
                ]);
            }
        }

        // END node — hanya untuk approved
        if ($scenario === 'approved') {
            TblProcessRouteLog::create([
                'idtblapproval_request'  => $req->idtblapproval_request,
                'idtblprocess_instance'  => $instance->idtblprocess_instance,
                'idtblflow_step'         => $endNode->idtblflow_step,
                'route_event'            => 'PROCESS_COMPLETED',
                'node_type'              => 'END',
                'action_code'            => 'END',
                'condition_result'       => 1,
                'message'                => 'Semua approval selesai. Status: APPROVED.',
                'created_by'             => 'SYSTEM',
                'created_at'             => Carbon::now()->subHours(rand(1, 6)),
            ]);
        }
    }
}
