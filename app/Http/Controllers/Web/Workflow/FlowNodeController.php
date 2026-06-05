<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\FlowNodeRequest;
use App\Models\TblFlowStep;
use App\Models\TblFlowVersion;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FlowNodeController — CRUD node (tblflow_step) per flow_version.
 *
 * Proteksi:
 *  - Jika flow_version sudah ACTIVE *dan* sudah dipakai approval request
 *    → semua mutasi diblokir. User harus Clone.
 *  - Jika flow_version ACTIVE tapi belum dipakai → masih boleh edit
 *    (admin harus re-validate + re-deploy).
 *  - condition_json divalidasi secara struktural oleh FlowNodeRequest.
 */
class FlowNodeController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(TblFlowVersion $flow_version): View
    {
        $nodes = $flow_version->nodes()
            ->withCount('activeAssigneeRules')
            ->get();
        return view('workflow.flow_node.index', [
            'version' => $flow_version->load('flowDefinition'),
            'nodes'   => $nodes,
        ]);
    }

    public function create(TblFlowVersion $flow_version): View
    {
        $this->abortIfLocked($flow_version);
        return view('workflow.flow_node.create', ['version' => $flow_version->load('flowDefinition')]);
    }

    public function store(FlowNodeRequest $request, TblFlowVersion $flow_version): RedirectResponse
    {
        $this->abortIfLocked($flow_version);

        $data = $request->validated();
        $data = $this->mergeNodeConfig($data, null);
        $data['idtblflow_version'] = $flow_version->idtblflow_version;
        $data['condition_json']    = $this->parseConditionJson($data['condition_json_raw'] ?? null);
        // step_code = node_code (keduanya bermakna sama, step_code wajib NOT NULL di DB)
        $data['step_code']         = $data['node_code'];
        // step_order: pakai input atau auto increment dari max yang ada
        $maxOrder = $flow_version->nodes()->max('step_order') ?? 0;
        $data['step_order']        = $data['step_order'] ?? ($maxOrder + 10);
        // Kolom NOT NULL di DB — pastikan tidak null (START/END/DECISION tidak butuh approval_mode)
        $data['approval_mode']     = $data['approval_mode']   ?: 'ANY';
        $data['reject_behavior']   = $data['reject_behavior'] ?? 'END_REJECTED';
        unset($data['condition_json_raw']);

        $node = TblFlowStep::create($data);
        $this->audit->recordCreated($node, "Node '{$node->node_code}' dibuat di version #{$flow_version->idtblflow_version}.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-node.index', $flow_version->idtblflow_version)
            ->with('status', "Node '{$node->node_code}' berhasil dibuat.");
    }

    public function edit(TblFlowVersion $flow_version, TblFlowStep $flow_step): View
    {
        abort_if($flow_step->idtblflow_version !== $flow_version->idtblflow_version, 404);
        return view('workflow.flow_node.edit', [
            'version'  => $flow_version->load('flowDefinition'),
            'item'     => $flow_step->load('activeAssigneeRules'),
            'isLocked' => $this->isLocked($flow_version),
        ]);
    }

    public function update(FlowNodeRequest $request, TblFlowVersion $flow_version, TblFlowStep $flow_step): RedirectResponse
    {
        abort_if($flow_step->idtblflow_version !== $flow_version->idtblflow_version, 404);
        $this->abortIfLocked($flow_version);

        $original = $flow_step->getOriginal();
        $data = $request->validated();
        $data = $this->mergeNodeConfig($data, $flow_step);
        $data['condition_json'] = $this->parseConditionJson($data['condition_json_raw'] ?? null);
        // step_code selalu sync dengan node_code
        $data['step_code']       = $data['node_code'];
        // Kolom NOT NULL di DB
        $data['approval_mode']   = $data['approval_mode']   ?: 'ANY';
        $data['reject_behavior'] = $data['reject_behavior'] ?? 'END_REJECTED';
        unset($data['condition_json_raw']);

        $flow_step->fill($data);
        $flow_step->save();

        if ($flow_step->wasChanged()) {
            $this->audit->recordUpdated($flow_step, $original, "Node '{$flow_step->node_code}' diubah.");
            $this->invalidateVersion($flow_version);
        }

        return redirect()->route('workflow.flow-node.index', $flow_version->idtblflow_version)
            ->with('status', "Node '{$flow_step->node_code}' diubah.");
    }

    public function destroy(TblFlowVersion $flow_version, TblFlowStep $flow_step): RedirectResponse
    {
        abort_if($flow_step->idtblflow_version !== $flow_version->idtblflow_version, 404);
        $this->abortIfLocked($flow_version);

        // Tidak boleh hapus jika ada edges yang reference node ini
        $refCount = \App\Models\TblFlowTransition::where('idtblflow_step_from', $flow_step->idtblflow_step)
            ->orWhere('idtblflow_step_to', $flow_step->idtblflow_step)
            ->count();
        if ($refCount > 0) {
            return back()->with('error', "Node '{$flow_step->node_code}' tidak dapat dihapus karena masih direferensikan oleh {$refCount} edge. Hapus edge-nya dulu.");
        }

        $code = $flow_step->node_code;
        $flow_step->delete();
        $this->audit->recordEvent('tblflow_step', null, 'MASTER_DEACTIVATED',
            "Node '{$code}' dihapus dari version #{$flow_version->idtblflow_version}.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-node.index', $flow_version->idtblflow_version)
            ->with('status', "Node '{$code}' dihapus.");
    }

    // -------------------------------------------------------

    private function isLocked(TblFlowVersion $v): bool
    {
        // #16: lock otoritatif (ACTIVE OR in-use OR ada instance RUNNING). Sebelumnya
        // `isActive() && isInUse()` → version yang sudah di-INACTIVE-kan oleh deploy
        // versi baru lolos lock padahal instance lama masih RUNNING → edit/hapus node
        // merusak jalur approval in-flight.
        return $v->isLocked();
    }

    private function abortIfLocked(TblFlowVersion $v): void
    {
        if ($this->isLocked($v)) {
            $count = $v->approvalRequestsUsingThisVersion()->count();
            abort(403, "Version ini sudah ACTIVE dan dipakai oleh {$count} approval request. Buat Clone untuk melakukan perubahan.");
        }
    }

    private function invalidateVersion(TblFlowVersion $v): void
    {
        if ($v->validation_status !== TblFlowVersion::VALIDATION_DRAFT) {
            $v->validation_status  = TblFlowVersion::VALIDATION_DRAFT;
            $v->validation_message = 'Flow diubah setelah validasi terakhir. Jalankan validasi ulang.';
            $v->validated_at       = null;
            $v->save();
        }
    }

    private function parseConditionJson(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        $decoded = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    /**
     * Bangun node_config_json dari input form (pertahankan key lain yang sudah ada):
     *  - editable_fields_raw (textarea, 1 path/baris) → editable_fields + flag allow_edit_payload
     *  - callback_on_enter (checkbox) + callback_event_code → callback per-node saat node dimasuki
     */
    private function mergeNodeConfig(array $data, ?TblFlowStep $existing): array
    {
        $cfg = (is_array($existing?->node_config_json) ? $existing->node_config_json : []);

        // ── editable_fields (textarea selalu terkirim → array_key_exists true) ──
        if (array_key_exists('editable_fields_raw', $data)) {
            $paths = collect(preg_split('/\r\n|\r|\n/', (string) ($data['editable_fields_raw'] ?? '')))
                ->map(fn ($l) => trim($l))->filter()->unique()->values()->all();
            if (empty($paths)) {
                unset($cfg['editable_fields']);
            } else {
                $cfg['editable_fields'] = $paths;
            }
            $data['allow_edit_payload'] = ! empty($paths);
        }
        unset($data['editable_fields_raw']);

        // ── callback per-node (checkbox + hidden 0 → selalu terkirim) ──
        if (array_key_exists('callback_on_enter', $data)) {
            if (filter_var($data['callback_on_enter'], FILTER_VALIDATE_BOOLEAN)) {
                $cfg['callback_on_enter'] = true;
                $ec = trim((string) ($data['callback_event_code'] ?? ''));
                if ($ec !== '') {
                    $cfg['callback_event_code'] = $ec;
                } else {
                    unset($cfg['callback_event_code']);
                }
            } else {
                unset($cfg['callback_on_enter'], $cfg['callback_event_code']);
            }
        }
        unset($data['callback_on_enter'], $data['callback_event_code']);

        $data['node_config_json'] = $cfg ?: null;

        return $data;
    }
}
