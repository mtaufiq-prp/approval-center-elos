<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\FlowVersionRequest;
use App\Models\TblFlowDefinition;
use App\Models\TblFlowVersion;
use App\Services\AuditTrailService;
use App\Services\FlowValidationService;
use App\Services\FlowVersionDeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FlowVersionController extends Controller
{
    public function __construct(
        private AuditTrailService            $audit,
        private FlowValidationService        $validator,
        private FlowVersionDeploymentService $deployer,
    ) {}

    public function index(TblFlowDefinition $flow_definition): View
    {
        $items = TblFlowVersion::where('idtblflow_definition', $flow_definition->idtblflow_definition)
            ->withCount(['steps', 'transitions', 'approvalRequestsUsingThisVersion as in_use_count'])
            ->orderByDesc('version_no')
            ->paginate(15);
        return view('workflow.flow_version.index', ['definition' => $flow_definition, 'items' => $items]);
    }

    public function create(TblFlowDefinition $flow_definition): View
    {
        $nextVersion = (TblFlowVersion::where('idtblflow_definition', $flow_definition->idtblflow_definition)->max('version_no') ?? 0) + 1;
        return view('workflow.flow_version.create', [
            'definition' => $flow_definition,
            'nextVersion' => $nextVersion,
        ]);
    }

    public function store(FlowVersionRequest $request, TblFlowDefinition $flow_definition): RedirectResponse
    {
        $data = $request->validated();
        $data['idtblflow_definition'] = $flow_definition->idtblflow_definition;
        $data['status'] = TblFlowVersion::STATUS_DRAFT;
        $data['validation_status'] = TblFlowVersion::VALIDATION_DRAFT;

        $row = TblFlowVersion::create($data);
        $this->audit->recordCreated($row, "Flow Version v{$row->version_no} dibuat (DRAFT).");

        return redirect()->route('workflow.flow-version.show', $row->idtblflow_version)
            ->with('status', "Version v{$row->version_no} dibuat. Tambahkan node & edge, lalu Validate & Deploy.");
    }

    public function show(TblFlowVersion $flow_version): View
    {
        $flow_version->load(['flowDefinition.sourceApp', 'flowDefinition.documentType', 'steps.activeAssigneeRules', 'transitions']);
        return view('workflow.flow_version.show', ['version' => $flow_version]);
    }

    public function edit(TblFlowVersion $flow_version): View
    {
        if (! $flow_version->isDraft()) {
            // Hanya DRAFT yang boleh diedit metadata-nya (versi ACTIVE/INACTIVE
            // tidak boleh diubah versi-numbernya / nama-nya untuk audit consistency).
            // Kita izinkan edit nama saja, bukan version_no.
        }
        return view('workflow.flow_version.edit', ['item' => $flow_version]);
    }

    public function update(FlowVersionRequest $request, TblFlowVersion $flow_version): RedirectResponse
    {
        $original = $flow_version->getOriginal();
        $data = $request->validated();
        // version_no tidak boleh diubah jika sudah ACTIVE / sudah dipakai
        if (! $flow_version->isDraft() && (int) $data['version_no'] !== $flow_version->version_no) {
            return back()->withErrors(['version_no' => 'version_no tidak dapat diubah untuk version non-DRAFT.'])->withInput();
        }
        $flow_version->fill($data); $flow_version->save();
        if ($flow_version->wasChanged()) {
            $this->audit->recordUpdated($flow_version, $original, "Flow Version v{$flow_version->version_no} diubah.");
        }
        return redirect()->route('workflow.flow-version.show', $flow_version->idtblflow_version)->with('status', 'Version diubah.');
    }

    /**
     * Jalankan FlowValidationService secara eksplisit (manual button).
     */
    public function runValidation(TblFlowVersion $flow_version): RedirectResponse
    {
        $result = $this->deployer->runValidation($flow_version);

        $msg = $result->isValid
            ? 'Flow VALID. ' . count($result->checks) . ' checks lulus. ' . count($result->warnings) . ' warning.'
            : 'Flow INVALID. ' . count($result->errors) . ' error: ' . implode(' | ', $result->errors);

        return redirect()->route('workflow.flow-version.show', $flow_version->idtblflow_version)
            ->with($result->isValid ? 'status' : 'error', $msg)
            ->with('validation_result', [
                'errors'   => $result->errors,
                'warnings' => $result->warnings,
                'checks'   => $result->checks,
            ]);
    }

    /**
     * Deploy version → ACTIVE (atomic).
     */
    public function deploy(Request $request, TblFlowVersion $flow_version): RedirectResponse
    {
        $note = (string) $request->input('deployment_note');

        try {
            $this->deployer->deploy($flow_version, $note);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('workflow.flow-version.show', $flow_version->idtblflow_version)
            ->with('status', "Version v{$flow_version->version_no} berhasil di-deploy menjadi ACTIVE.");
    }

    /**
     * Clone version baru dari version existing.
     * Wajib dipakai jika version asal sudah ACTIVE & sudah dipakai approval request.
     */
    public function cloneVersion(Request $request, TblFlowVersion $flow_version): RedirectResponse
    {
        $newName = $request->input('new_name');
        $new = $this->deployer->cloneVersion($flow_version, $newName);

        return redirect()->route('workflow.flow-version.show', $new->idtblflow_version)
            ->with('status', "Cloned ke version v{$new->version_no} (DRAFT). Silakan edit & deploy.");
    }

    /**
     * Flow Preview — Mermaid + tabel fallback.
     */
    public function preview(TblFlowVersion $flow_version): View
    {
        $flow_version->load(['flowDefinition', 'steps', 'transitions.stepFrom', 'transitions.stepTo']);
        return view('workflow.flow_version.preview', ['version' => $flow_version]);
    }
}
