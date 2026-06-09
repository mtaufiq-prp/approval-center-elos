<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tracking Approval — {{ $req->source_request_no ?? $req->idtblapproval_request }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{background:#f5f6f8}
        .track-wrap{max-width:900px;margin:0 auto;padding:1.5rem 1rem 3rem}
        .aproute{display:flex;align-items:flex-start;justify-content:center;overflow-x:auto;padding:1.4rem 1rem .5rem}
        .aproute-step{display:flex;align-items:flex-start;flex:0 0 auto}
        .aproute-node{display:flex;flex-direction:column;align-items:center;text-align:center;width:152px;padding:0 .3rem}
        .aproute-dot{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;
            font-size:1.15rem;font-weight:700;background:#fff;border:2px solid #e3e6ea;color:#868e96}
        .aproute-dot.is-done{background:#198754;border-color:#198754;color:#fff}
        .aproute-dot.is-current{background:#0d6efd;border-color:#0d6efd;color:#fff;box-shadow:0 0 0 4px rgba(13,110,253,.18)}
        .aproute-dot.is-rejected{background:#dc3545;border-color:#dc3545;color:#fff}
        .aproute-dot.is-returned{background:#ffc107;border-color:#ffc107;color:#fff}
        .aproute-line{flex:1 1 auto;min-width:34px;height:3px;background:#e3e6ea;border-radius:2px;margin-top:22px}
        .aproute-line.is-done{background:#198754}
        .aproute-name{font-weight:600;font-size:.85rem;margin-top:.55rem;color:#2b3035;line-height:1.2}
        .aproute-state{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:.15rem}
        .aproute-pic{font-size:.8rem;color:#495057;margin-top:.4rem;line-height:1.3;max-width:148px}
        .aproute-pic .lbl{display:block;font-size:.62rem;color:#adb5bd;text-transform:uppercase;letter-spacing:.05em;margin-bottom:1px}
        .aproute-time{font-size:.7rem;color:#9aa0a6;margin-top:.15rem}
    </style>
</head>
<body>
@php
    $statusColors = [
        'SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
        'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'
    ];
    $sc = $statusColors[$req->request_status] ?? 'secondary';
@endphp
<div class="track-wrap">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="text-muted small"><i class="bi bi-shield-check"></i> Approval Center — Tracking</div>
            <h5 class="mb-0 mt-1">{{ $req->title }}</h5>
            <div class="text-muted small">No: <code>{{ $req->source_request_no ?? $req->idtblapproval_request }}</code></div>
        </div>
        <span class="badge bg-{{ $sc }} fs-6">{{ $req->request_status }}</span>
    </div>

    {{-- Alur Persetujuan --}}
    @if(!empty($approvalRoute))
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-diagram-3"></i> Alur Persetujuan
            <span class="small text-muted float-end">
                <span class="me-2"><i class="bi bi-circle-fill text-success" style="font-size:.6rem"></i> Sudah</span>
                <span class="me-2"><i class="bi bi-circle-fill text-primary" style="font-size:.6rem"></i> Sekarang</span>
                <span><i class="bi bi-circle text-secondary" style="font-size:.6rem"></i> Akan</span>
            </span>
        </div>
        <div class="card-body py-2">
            <div class="aproute">
                @foreach($approvalRoute as $i => $step)
                    @php
                        $node  = $step['node'];
                        $t     = $step['task'];
                        $state = $step['state'];
                        $cfg = [
                            'done'     => ['is-done',     'Disetujui',    'success'],
                            'current'  => ['is-current',  'Sekarang',     'primary'],
                            'future'   => ['',            'Akan',         'secondary'],
                            'rejected' => ['is-rejected', 'Ditolak',      'danger'],
                            'returned' => ['is-returned', 'Dikembalikan', 'warning'],
                        ];
                        [$dotCls,$stateLbl,$txtColor] = $cfg[$state] ?? ['','—','secondary'];
                    @endphp
                    @if(!$loop->first)
                        @php $prev = $approvalRoute[$i-1]['state']; $lineDone = in_array($prev, ['done','rejected','returned']); @endphp
                        <div class="aproute-step"><div class="aproute-line {{ $lineDone ? 'is-done' : '' }}"></div></div>
                    @endif
                    <div class="aproute-step">
                        <div class="aproute-node">
                            <div class="aproute-dot {{ $dotCls }}">{{ $i + 1 }}</div>
                            <div class="aproute-name">@nodeLabel($node->step_name)</div>
                            <div class="aproute-state text-{{ $txtColor }}">{{ $stateLbl }}</div>
                            @if($t)
                                <div class="aproute-pic">
                                    <span class="lbl">Oleh</span>
                                    {{ optional($t->completedBy)->full_name ?? optional($t->completedBy)->user_ref }}
                                </div>
                                <div class="aproute-time">{{ $t->completed_at?->format('d/m H:i') }}</div>
                            @elseif(!empty($step['auto']))
                                <div class="aproute-pic">
                                    <span class="lbl">Oleh (auto)</span>
                                    {{ $step['auto']['name'] }}
                                </div>
                                <div class="aproute-time">{{ optional($step['auto']['at'])->format('d/m H:i') }}</div>
                            @elseif(!empty($step['pending']))
                                <div class="aproute-pic">
                                    <span class="lbl">{{ $state === 'current' ? 'Pending di' : 'Calon' }}</span>
                                    @foreach(array_slice($step['pending'], 0, 2) as $p){{ \Illuminate\Support\Str::limit($p['name'] ?: $p['ref'], 20) }}@if(!$loop->last), @endif @endforeach
                                    @if(count($step['pending']) > 2) <span class="text-muted">+{{ count($step['pending']) - 2 }}</span>@endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Data Dokumen --}}
    @include('partials._context_renderer', [
        'payloadJson'   => $payloadJson,
        'contextJson'   => $contextJson,
        'formSchema'    => optional($req->documentType)->form_schema ?? [],
        'docTypeName'   => optional($req->documentType)->doc_name,
        'sourceBaseUrl' => optional($req->sourceApp)->base_url,
    ])

    {{-- Riwayat Keputusan --}}
    @if($history->isNotEmpty())
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white small fw-semibold"><i class="bi bi-clock-history"></i> Riwayat Keputusan</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light small"><tr>
                        <th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Catatan</th>
                    </tr></thead>
                    <tbody class="small">
                    @foreach($history as $h)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $h->actor_name ? $h->actor_name.' - '.$h->actor_ref : $h->actor_ref }}</td>
                            <td>
                                <span class="badge bg-{{ match($h->action_code) {
                                    'APPROVE','AUTO_APPROVE'=>'success','REJECT'=>'danger',
                                    'RETURN'=>'warning','CANCEL'=>'secondary',default=>'info'} }}">
                                    {{ $h->action_code }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $h->action_note }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="text-center text-muted small mt-4">
        <i class="bi bi-lock"></i> Halaman read-only — Approval Center PT Propan Raya ICC
    </div>
</div>
</body>
</html>
