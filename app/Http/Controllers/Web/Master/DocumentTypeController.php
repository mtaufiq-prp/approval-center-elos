<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\DocumentTypeRequest;
use App\Models\TblDocumentType;
use App\Models\TblSourceApp;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblDocumentType::with('sourceApp');
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('doc_code', 'like', "%$s%")->orWhere('doc_name', 'like', "%$s%"));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }
        $items = $q->orderBy('doc_code')->paginate(15)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get();
        return view('master.document_type.index', compact('items', 'sourceApps'));
    }

    public function create(): View
    {
        return view('master.document_type.create', ['sourceApps' => TblSourceApp::where('is_active', 1)->orderBy('app_code')->get()]);
    }

    public function store(DocumentTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active']            = (bool) ($data['is_active'] ?? true);
        $data['form_schema']          = $this->parseSchema($data['form_schema'] ?? null);
        $data['sample_context_json']  = $this->parseSchema($data['sample_context_json'] ?? null);
        $row = TblDocumentType::create($data);
        $this->audit->recordCreated($row, "Document Type {$row->doc_code} dibuat.");
        return redirect()->route('master.document-type.index')->with('status', "Doc Type {$row->doc_code} dibuat.");
    }

    public function edit(TblDocumentType $document_type): View
    {
        return view('master.document_type.edit', [
            'item'       => $document_type,
            'sourceApps' => TblSourceApp::orderBy('app_code')->get(),
        ]);
    }

    public function update(DocumentTypeRequest $request, TblDocumentType $document_type): RedirectResponse
    {
        $original = $document_type->getOriginal();
        $data = $request->validated();
        $data['is_active']            = (bool) ($data['is_active'] ?? $document_type->is_active);
        $data['form_schema']          = $this->parseSchema($data['form_schema'] ?? null);
        $data['sample_context_json']  = $this->parseSchema($data['sample_context_json'] ?? null);
        $document_type->fill($data); $document_type->save();
        if ($document_type->wasChanged()) {
            $this->audit->recordUpdated($document_type, $original, "Doc Type {$document_type->doc_code} diubah.");
        }
        return redirect()->route('master.document-type.index')->with('status', "Doc Type diubah.");
    }

    private function parseSchema(?string $raw): ?array
    {
        if (! $raw || trim($raw) === '') return null;
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function destroy(TblDocumentType $document_type): RedirectResponse
    {
        if ($document_type->is_active) {
            $document_type->is_active = false; $document_type->save();
            $this->audit->recordDeactivated($document_type);
        } else {
            $document_type->is_active = true; $document_type->save();
            $this->audit->recordActivated($document_type);
        }
        return back()->with('status', 'Status doc type diubah.');
    }
}
