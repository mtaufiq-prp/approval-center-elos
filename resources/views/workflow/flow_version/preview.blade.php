@extends('layouts.workflow')
@section('title','Flow Preview')
@push('styles')
<style>
.mermaid-container { background:#f8f9fa; border:1px solid #dee2e6; border-radius:4px; padding:16px; min-height:200px; }
#mermaid-diagram svg { max-width:100%; height:auto; }
#mermaid-fallback { display:none; }
.node-badge-START{background:#198754;color:#fff;}
.node-badge-END{background:#dc3545;color:#fff;}
.node-badge-APPROVAL{background:#0d6efd;color:#fff;}
.node-badge-DECISION{background:#ffc107;color:#000;}
</style>
@endpush

@section('workflow_content')
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-definition.index') }}">Flow Definition</a></li>
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.index', $version->idtblflow_definition) }}">{{ optional($version->flowDefinition)->flow_code }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.show', $version->idtblflow_version) }}">v{{ $version->version_no }}</a></li>
    <li class="breadcrumb-item active">Preview</li>
</ol></nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-eye"></i> Flow Preview: {{ optional($version->flowDefinition)->flow_code }} v{{ $version->version_no }}</h5>
    <div>
        <button id="btn-toggle-view" class="btn btn-sm btn-outline-secondary">Tampilkan Tabel</button>
    </div>
</div>

{{-- ===== DIAGRAM MERMAID ===== --}}
<div class="card shadow-sm mb-3" id="diagram-card">
    <div class="card-header bg-light small fw-semibold d-flex justify-content-between">
        <span>Diagram (Mermaid.js)</span>
        <span class="text-muted" id="mermaid-status">Memuat...</span>
    </div>
    <div class="card-body p-2">
        <div class="mermaid-container">
            <div id="mermaid-diagram"></div>
        </div>
        <div id="mermaid-error" class="alert alert-warning mt-2 small d-none">
            Diagram tidak dapat dirender (Mermaid.js tidak tersedia / koneksi internet terbatas).
            Lihat Tabel di bawah untuk referensi flow.
            <br><strong>Untuk production offline:</strong> salin
            <code>mermaid.min.js</code> ke <code>public/vendor/mermaid/mermaid.min.js</code>
            dari <a href="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js" target="_blank">CDN jsdelivr</a>.
        </div>
    </div>
</div>

{{-- ===== TABEL FALLBACK ===== --}}
<div id="table-view" style="display:none">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light small fw-semibold">Nodes ({{ $version->steps->count() }})</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light small"><tr>
                        <th>node_code</th><th>step_name</th><th>Type</th><th>Gateway</th><th>step_order</th>
                    </tr></thead>
                    <tbody class="small">
                    @foreach ($version->steps->sortBy('step_order') as $n)
                        <tr>
                            <td><code>{{ $n->node_code }}</code></td>
                            <td>{{ $n->step_name }}</td>
                            <td><span class="badge node-badge-{{ $n->step_type }}">{{ $n->step_type }}</span></td>
                            <td>{{ $n->gateway_type !== 'NONE' ? $n->gateway_type : '-' }}</td>
                            <td>{{ $n->step_order }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light small fw-semibold">Edges ({{ $version->transitions->count() }})</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light small"><tr>
                        <th>From</th><th>Action</th><th>To</th><th>Condition</th><th>Priority</th><th>Default</th><th>Status</th>
                    </tr></thead>
                    <tbody class="small">
                    @foreach ($version->transitions->sortBy('priority_no') as $e)
                        <tr>
                            <td><code>{{ optional($e->stepFrom)->node_code }}</code></td>
                            <td><span class="badge bg-light text-dark border">{{ $e->action_code }}</span></td>
                            <td><code>{{ $e->idtblflow_step_to ? optional($e->stepTo)->node_code : '[END]' }}</code></td>
                            <td class="text-muted small">
                                @if ($e->condition_json)
                                    <code class="small">{{ Str::limit(json_encode($e->condition_json), 60) }}</code>
                                @else
                                    <em>always</em>
                                @endif
                            </td>
                            <td>{{ $e->priority_no }}</td>
                            <td>@if($e->is_default)<span class="badge bg-info">DEFAULT</span>@endif</td>
                            <td>@if($e->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Build Mermaid flowchart syntax dari data server
const MERMAID_DEF = `flowchart TD
@php
    $nodes = $version->steps->sortBy('step_order');
    $edges = $version->transitions->where('is_active', true)->sortBy('priority_no');

    foreach ($nodes as $n):
        $code  = $n->node_code ?? 'N'.$n->idtblflow_step;
        $label = addslashes($n->step_name);
        $type  = $n->step_type;
        if ($type === 'START'):
            echo "    {$code}(({$label}))\n";
        elseif ($type === 'END'):
            echo "    {$code}(({$label}))\n";
        elseif ($type === 'DECISION'):
            echo "    {$code}{{{$label}}}\n";
        else:
            echo "    {$code}[{$label}]\n";
        endif;
    endforeach;

    foreach ($edges as $e):
        $fromCode = optional($e->stepFrom)->node_code ?? 'N'.$e->idtblflow_step_from;
        $toCode   = $e->idtblflow_step_to ? (optional($e->stepTo)->node_code ?? 'N'.$e->idtblflow_step_to) : 'END';
        $action   = addslashes($e->action_code ?? '');
        $cond     = '';
        if ($e->condition_json):
            $cond = ' [cond]';
        endif;
        echo "    {$fromCode} -->|{$action}{$cond}| {$toCode}\n";
    endforeach;

    // Color styling per node type
    foreach ($nodes as $n):
        $code = $n->node_code ?? 'N'.$n->idtblflow_step;
        $color = match($n->step_type){
            'START'    => 'fill:#198754,color:#fff',
            'END'      => 'fill:#dc3545,color:#fff',
            'DECISION' => 'fill:#ffc107,color:#000',
            'APPROVAL' => 'fill:#0d6efd,color:#fff',
            default    => 'fill:#6c757d,color:#fff',
        };
        echo "    style {$code} {$color}\n";
    endforeach;
@endphp`;

// Try CDN mermaid, fallback to local vendor if CDN fails
function loadMermaid(src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src; s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
    });
}

async function renderMermaid() {
    const vendorLocal  = '/vendor/mermaid/mermaid.min.js';
    const vendorCDN    = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js';

    try {
        // Coba lokal dulu (untuk production offline)
        await loadMermaid(vendorLocal);
    } catch {
        try {
            await loadMermaid(vendorCDN);
        } catch {
            document.getElementById('mermaid-status').textContent = 'Tidak tersedia';
            document.getElementById('mermaid-error').classList.remove('d-none');
            document.getElementById('mermaid-diagram').style.display = 'none';
            // Auto-tampilkan tabel fallback
            document.getElementById('table-view').style.display = '';
            document.getElementById('diagram-card').style.display = 'none';
            document.getElementById('btn-toggle-view').textContent = 'Tampilkan Diagram';
            return;
        }
    }

    try {
        mermaid.initialize({ startOnLoad: false, theme: 'base', flowchart: { curve: 'basis' } });
        const el = document.getElementById('mermaid-diagram');
        const { svg } = await mermaid.render('mermaid-svg', MERMAID_DEF);
        el.innerHTML = svg;
        document.getElementById('mermaid-status').textContent = 'OK';
    } catch (e) {
        console.error('Mermaid render error:', e);
        document.getElementById('mermaid-status').textContent = 'Error render';
        document.getElementById('mermaid-error').classList.remove('d-none');
    }
}

renderMermaid();

// Toggle button
document.getElementById('btn-toggle-view').addEventListener('click', function() {
    const tv = document.getElementById('table-view');
    const dc = document.getElementById('diagram-card');
    const show = tv.style.display === 'none';
    tv.style.display = show ? '' : 'none';
    dc.style.display = show ? 'none' : '';
    this.textContent = show ? 'Tampilkan Diagram' : 'Tampilkan Tabel';
});
</script>
@endpush
@endsection
