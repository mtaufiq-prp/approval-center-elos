<?php

namespace App\Services;

use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Models\TblStepAssigneeRule;
use Illuminate\Support\Facades\DB;

/**
 * FlowBuilderSaveService
 *
 * Sync nodes/edges dari canvas React Flow ke database.
 *
 * Algoritma 2-pass:
 *   Pass 1: Insert/update nodes → dapatkan mapping frontend_id → idtblflow_step
 *   Pass 2: Insert/update edges menggunakan mapping dari Pass 1
 *   Pass 3: Sync assignee rules untuk APPROVAL nodes
 *   Pass 4: Handle deletes (validasi dulu)
 *   Pass 5: Update diagram_json + invalidate validation_status
 *
 * Semua dalam satu DB transaction.
 */
class FlowBuilderSaveService
{
    public function __construct(
        private AuditTrailService     $audit,
        private FlowBuilderDataService $dataService,
    ) {}

    /**
     * Simpan canvas ke database.
     *
     * @param TblFlowVersion $version
     * @param array $payload  {nodes, edges, deleted_node_ids, deleted_edge_ids, diagram_json}
     * @return array  {success, message, errors, data}
     */
    public function save(TblFlowVersion $version, array $payload): array
    {
        // Lock check
        if ($this->dataService->isLocked($version)) {
            return [
                'success' => false,
                'message' => 'Flow version ini locked. Buat Clone untuk melakukan perubahan.',
                'errors'  => ['lock' => 'Version sudah ACTIVE dan pernah digunakan.'],
            ];
        }

        $errors = [];
        // Snapshot diagram SEBELUM perubahan untuk audit diff (#101)
        $beforeDiagram = $version->diagram_json;

        try {
            DB::transaction(function () use ($version, $payload, &$errors, $beforeDiagram) {

                $nodes          = $payload['nodes']            ?? [];
                $edges          = $payload['edges']            ?? [];
                $deletedNodeIds = $payload['deleted_node_ids'] ?? [];
                $deletedEdgeIds = $payload['deleted_edge_ids'] ?? [];
                $diagramJson    = $payload['diagram_json']     ?? null;

                // -------------------------------------------------------
                // PASS 1 — Handle deleted edges first (before nodes)
                // -------------------------------------------------------
                if (! empty($deletedEdgeIds)) {
                    TblFlowTransition::where('idtblflow_version', $version->idtblflow_version)
                        ->whereIn('idtblflow_transition', $deletedEdgeIds)
                        ->delete();
                }

                // -------------------------------------------------------
                // PASS 2 — Handle deleted nodes
                // Validasi: node tidak boleh dihapus jika masih ada edge
                // yang tidak ikut dihapus dalam payload ini.
                // -------------------------------------------------------
                if (! empty($deletedNodeIds)) {
                    // Cek apakah ada edge ke/dari node yang masih aktif
                    $edgesStillExist = TblFlowTransition::where('idtblflow_version', $version->idtblflow_version)
                        ->where(function ($q) use ($deletedNodeIds) {
                            $q->whereIn('idtblflow_step_from', $deletedNodeIds)
                              ->orWhereIn('idtblflow_step_to', $deletedNodeIds);
                        })->count();

                    if ($edgesStillExist > 0) {
                        throw new \RuntimeException(
                            "Tidak bisa menghapus node karena masih ada edge yang terhubung. Hapus edge-nya dulu."
                        );
                    }

                    // Hapus assignee rules node yang dihapus
                    TblStepAssigneeRule::whereIn('idtblflow_step', $deletedNodeIds)->delete();
                    // Hapus node
                    TblFlowStep::where('idtblflow_version', $version->idtblflow_version)
                        ->whereIn('idtblflow_step', $deletedNodeIds)
                        ->delete();
                }

                // -------------------------------------------------------
                // PASS 2b — Reconcile ke payload SEBELUM upsert (desired state penuh)
                // Builder mengirim SELURUH canvas, tetapi deleted_node_ids/
                // deleted_edge_ids sering kosong ([]). Akibatnya:
                //   (a) penghapusan di canvas tak persist → "balik lagi" saat reload;
                //   (b) orphan lama bentrok transition_code dgn edge yang digambar
                //       ulang (uq_version_code → 1062) saat INSERT di PASS 4.
                // Solusi: hapus baris DB versi ini yang TIDAK direferensikan payload
                // SEBELUM upsert — orphan bersih dulu, baru insert/update (anti-1062).
                // keep = id yang DIKIRIM payload; node/edge baru (id null) belum ada
                // di DB → tak terpengaruh. Urutan: edge dulu (FK), lalu rule + step.
                // Guard !empty($nodes) cegah payload kosong menghapus seluruh flow.
                // -------------------------------------------------------
                if (! empty($nodes)) {
                    $keepStepIds = array_values(array_filter(array_map(
                        fn ($n) => $n['idtblflow_step'] ?? null, $nodes
                    )));
                    $keepEdgeIds = array_values(array_filter(array_map(
                        fn ($e) => $e['idtblflow_transition'] ?? null, $edges
                    )));

                    TblFlowTransition::where('idtblflow_version', $version->idtblflow_version)
                        ->when(! empty($keepEdgeIds), fn ($q) => $q->whereNotIn('idtblflow_transition', $keepEdgeIds))
                        ->delete();

                    $staleStepIds = TblFlowStep::where('idtblflow_version', $version->idtblflow_version)
                        ->when(! empty($keepStepIds), fn ($q) => $q->whereNotIn('idtblflow_step', $keepStepIds))
                        ->pluck('idtblflow_step');

                    if ($staleStepIds->isNotEmpty()) {
                        TblStepAssigneeRule::whereIn('idtblflow_step', $staleStepIds)->delete();
                        TblFlowStep::whereIn('idtblflow_step', $staleStepIds)->delete();
                    }
                }

                // -------------------------------------------------------
                // PASS 3 — Upsert nodes
                // Map frontend_id → idtblflow_step untuk dipakai pass 4
                // -------------------------------------------------------
                $nodeIdMap = []; // 'node_123' atau 'node_new_abc' → idtblflow_step

                $validStepTypes    = ['START','END','APPROVAL','DECISION','REVIEW','NOTIFICATION','SYSTEM'];
                $validGatewayTypes = ['NONE','EXCLUSIVE','INCLUSIVE','PARALLEL'];
                $validApprModes    = ['ANY','ALL','SEQUENTIAL'];

                foreach ($nodes as $n) {
                    $frontendId   = $n['id'];
                    $dbId         = $n['idtblflow_step'] ?? null;
                    $data         = $n['data'] ?? [];
                    $position     = $n['position'] ?? ['x' => 100, 'y' => 100];

                    // Validasi enum per-field (#18)
                    $rawStepType = strtoupper($data['step_type'] ?? $n['type'] ?? 'APPROVAL');
                    if (! in_array($rawStepType, $validStepTypes, true)) {
                        throw new \InvalidArgumentException("step_type '{$rawStepType}' tidak valid untuk node '{$n['id']}'.");
                    }
                    $rawGateway  = strtoupper($data['gateway_type'] ?? 'NONE');
                    if (! in_array($rawGateway, $validGatewayTypes, true)) $rawGateway = 'NONE';
                    $rawApprMode = strtoupper(($data['approval_mode'] ?? null) ?: 'ANY');
                    if (! in_array($rawApprMode, $validApprModes, true)) $rawApprMode = 'ANY';
                    if (empty(trim($data['node_code'] ?? ''))) {
                        throw new \InvalidArgumentException("node_code wajib diisi untuk node '{$n['id']}'.");
                    }

                    $stepData = [
                        'node_code'    => $n['node_code']   ?? ($data['node_code'] ?? 'NODE'),
                        'step_code'    => $n['node_code']   ?? ($data['node_code'] ?? 'NODE'),
                        'step_name'    => $data['step_name'] ?? $n['label'] ?? 'Node',
                        'step_type'    => $rawStepType,
                        'gateway_type' => $rawGateway,
                        'approval_mode'=> $rawApprMode,
                        'reject_behavior' => $data['reject_behavior'] ?? 'END_REJECTED',
                        'sla_hours'    => $data['sla_hours']    ?: null,
                        'instruction'  => $data['instruction']  ?: null,
                        'condition_json' => isset($data['condition_json'])
                            ? (is_array($data['condition_json']) ? json_encode($data['condition_json']) : ($data['condition_json'] ?: null))
                            : null,
                        'node_config_json' => isset($data['node_config_json'])
                            ? (is_array($data['node_config_json']) ? json_encode($data['node_config_json']) : ($data['node_config_json'] ?: null))
                            : null,
                        'pos_x'        => (int) ($position['x'] ?? 100),
                        'pos_y'        => (int) ($position['y'] ?? 100),
                        'step_order'   => $n['data']['step_order'] ?? 10,
                        'idtblflow_version' => $version->idtblflow_version,
                    ];

                    if ($dbId) {
                        // UPDATE existing
                        TblFlowStep::where('idtblflow_step', $dbId)
                            ->where('idtblflow_version', $version->idtblflow_version)
                            ->update($stepData);
                        $nodeIdMap[$frontendId] = $dbId;
                    } else {
                        // INSERT new
                        $step = TblFlowStep::create($stepData);
                        $nodeIdMap[$frontendId] = $step->idtblflow_step;
                    }
                }

                // -------------------------------------------------------
                // PASS 4 — Upsert edges
                // Resolve source/target dari nodeIdMap
                // -------------------------------------------------------
                // Pre-load semua step yang terlibat di edges agar tidak N+1 (#26)
                $allStepIds = collect($nodeIdMap)->values()->filter()->unique();
                $stepCodeMap = TblFlowStep::whereIn('idtblflow_step', $allStepIds)
                    ->pluck('node_code', 'idtblflow_step');

                foreach ($edges as $e) {
                    $frontendEdgeId = $e['id'];
                    $dbEdgeId       = $e['idtblflow_transition'] ?? null;
                    $data           = $e['data'] ?? [];

                    // Resolve source
                    $sourceKey = $e['source'];
                    $sourceDbId = $nodeIdMap[$sourceKey]
                        ?? (preg_match('/node_(\d+)/', $sourceKey, $m) ? (int) $m[1] : null);

                    if (! $sourceDbId) continue; // skip edge invalid

                    // Resolve target
                    $targetKey  = $e['target'];
                    $targetDbId = null;
                    if ($targetKey && $targetKey !== 'END_VIRTUAL') {
                        $targetDbId = $nodeIdMap[$targetKey]
                            ?? (preg_match('/node_(\d+)/', $targetKey, $m) ? (int) $m[1] : null);
                    }

                    // Auto-generate transition_code — gunakan cache, bukan find() (#26).
                    // Fallback ke id node (bukan literal 'FROM'/'TO') + SELALU suffix id from→to
                    // agar UNIK per edge: satu node bisa punya banyak edge keluar (percabangan),
                    // dan code yang tidak ter-resolve tidak boleh bentrok di uq_(version,transition_code).
                    $fromCode = $stepCodeMap->get($sourceDbId) ?? ('N' . $sourceDbId);
                    $toCode   = $targetDbId ? ($stepCodeMap->get($targetDbId) ?? ('N' . $targetDbId)) : 'END';
                    $autoCode = strtoupper("{$fromCode}_TO_{$toCode}_{$sourceDbId}_" . ($targetDbId ?: 'END'));

                    $edgeData = [
                        'idtblflow_version'    => $version->idtblflow_version,
                        'idtblflow_step_from'  => $sourceDbId,
                        'idtblflow_step_to'    => $targetDbId,
                        // Abaikan transition_code dari frontend: builder mengirim placeholder
                        // 'FROM_TO_TO' untuk SEMUA edge → bentrok di uq_(version,code). Selalu
                        // pakai autoCode unik berbasis id from→to (transition_code internal saja).
                        'transition_code'      => $autoCode,
                        'transition_name'      => $data['transition_name']  ?: null,
                        'transition_type'      => $data['transition_type']  ?? 'NORMAL',
                        'action_code'          => $data['action_code']      ?? 'APPROVE',
                        'priority_no'          => (int) ($data['priority_no'] ?? 100),
                        'is_default'           => (bool) ($data['is_default'] ?? false),
                        'is_active'            => (bool) ($data['is_active']  ?? true),
                        'final_status'         => $data['final_status']     ?: null,
                        'condition_json'       => isset($data['condition_json'])
                            ? (is_array($data['condition_json']) ? json_encode($data['condition_json']) : ($data['condition_json'] ?: null))
                            : null,
                    ];

                    if ($dbEdgeId) {
                        TblFlowTransition::where('idtblflow_transition', $dbEdgeId)
                            ->where('idtblflow_version', $version->idtblflow_version)
                            ->update($edgeData);
                    } else {
                        TblFlowTransition::create($edgeData);
                    }
                }

                // -------------------------------------------------------
                // PASS 5 — Sync assignee rules untuk APPROVAL nodes
                // -------------------------------------------------------
                foreach ($nodes as $n) {
                    $data = $n['data'] ?? [];
                    if (($data['step_type'] ?? $n['type'] ?? '') !== 'APPROVAL') continue;

                    $frontendId = $n['id'];
                    $dbStepId   = $nodeIdMap[$frontendId] ?? null;
                    if (! $dbStepId) continue;

                    $incomingRules = $data['assignee_rules'] ?? [];

                    // Hapus yang tidak ada di payload
                    $keepIds = array_filter(
                        array_column($incomingRules, 'idtblstep_assignee_rule')
                    );
                    TblStepAssigneeRule::where('idtblflow_step', $dbStepId)
                        ->when(! empty($keepIds), fn($q) => $q->whereNotIn('idtblstep_assignee_rule', $keepIds))
                        ->delete();

                    foreach ($incomingRules as $rule) {
                        $ruleDbId  = $rule['idtblstep_assignee_rule'] ?? null;
                        $ruleData  = [
                            'idtblflow_step'  => $dbStepId,
                            'assignee_type'   => $rule['assignee_type']  ?? 'USER',
                            'assignee_value'  => $rule['assignee_value'] ?? null,
                            'priority_no'     => (int) ($rule['priority_no'] ?? 1),
                            'is_required'     => (bool) ($rule['is_required'] ?? true),
                            'is_active'       => (bool) ($rule['is_active']   ?? true),
                            'condition_json'  => $rule['condition_json'] ?? null,
                        ];
                        if ($ruleDbId) {
                            TblStepAssigneeRule::where('idtblstep_assignee_rule', $ruleDbId)->update($ruleData);
                        } else {
                            TblStepAssigneeRule::create($ruleData);
                        }
                    }
                }

                // -------------------------------------------------------
                // PASS 6 — Update diagram_json + invalidate validation
                // -------------------------------------------------------
                $updateData = [
                    'validation_status'  => TblFlowVersion::VALIDATION_DRAFT,
                    'validation_message' => 'Canvas diubah. Jalankan Validate sebelum Deploy.',
                    'validated_at'       => null,
                ];
                if ($diagramJson !== null) {
                    $updateData['diagram_json'] = is_array($diagramJson)
                        ? $diagramJson
                        : json_decode($diagramJson, true);
                }
                $version->update($updateData);

                // -------------------------------------------------------
                // Audit
                // -------------------------------------------------------
                // Audit dengan before/after diagram_json agar perubahan routing/approver
                // dapat direkonstruksi (#101).
                $this->audit->recordChange(
                    entityType: 'FLOW_BUILDER',
                    entityId:   $version->idtblflow_version,
                    eventCode:  'SAVE_BUILDER',
                    oldValues:  ['diagram_json' => $beforeDiagram],
                    newValues:  [
                        'node_count'    => count($nodes),
                        'edge_count'    => count($edges),
                        'deleted_nodes' => count($deletedNodeIds),
                        'deleted_edges' => count($deletedEdgeIds),
                        'diagram_json'  => $updateData['diagram_json'] ?? $beforeDiagram,
                    ],
                    message:    "Builder saved: v{$version->version_no} ({$version->version_name}). Nodes: " . count($nodes) . ", Edges: " . count($edges) . ".",
                );
            });

        } catch (\InvalidArgumentException $e) {
            // Pesan validasi per-field aman ditampilkan ke admin builder
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => ['validation' => $e->getMessage()],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("FlowBuilderSave: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => 'Gagal menyimpan flow. Periksa konfigurasi atau hubungi administrator.',
                'errors'  => ['exception' => 'internal_error'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Flow berhasil disimpan.',
            'errors'  => $errors,
        ];
    }
}
