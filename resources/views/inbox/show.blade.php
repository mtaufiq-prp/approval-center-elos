@extends('layouts.app')
@section('title', 'Detail Task')

@section('content')
@php
    $req = $task->approvalRequest;
    $statusColors = [
        'SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
        'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'
    ];
    $sc = $statusColors[$req->request_status] ?? 'secondary';

    $isOpen = $task->task_status === 'OPEN';
    $decisionMeta = [
        'APPROVED'  => ['success', 'check-circle',          'Disetujui'],
        'REJECTED'  => ['danger',  'x-circle',              'Ditolak'],
        'RETURNED'  => ['warning', 'arrow-counterclockwise','Dikembalikan'],
        'CANCELLED' => ['secondary','slash-circle',         'Dibatalkan'],
        'SKIPPED'   => ['light',   'skip-forward',          'Dilewati'],
        'EXPIRED'   => ['dark',    'hourglass-bottom',      'Expired'],
    ];
    [$dColor,$dIcon,$dLabel] = $decisionMeta[$task->task_status] ?? ['info','info-circle',$task->task_status];
@endphp

<style>
    .card-collapsible{cursor:pointer;user-select:none}
    .card-collapsible .bi-chevron-down{transition:transform .25s ease}
    .card-collapsible[aria-expanded="false"] .bi-chevron-down{transform:rotate(-90deg)}

    /* ===== Alur Persetujuan (stepper) ===== */
    .aproute{display:flex;align-items:flex-start;justify-content:center;overflow-x:auto;padding:1.4rem 1rem .5rem}
    .aproute::-webkit-scrollbar{height:6px}
    .aproute::-webkit-scrollbar-thumb{background:#d4d9df;border-radius:4px}
    .aproute-step{display:flex;align-items:flex-start;flex:0 0 auto}
    .aproute-node{display:flex;flex-direction:column;align-items:center;text-align:center;width:152px;padding:0 .3rem}
    .aproute-dot{position:relative;width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;
        font-size:1.15rem;font-weight:700;background:#fff;border:2px solid #e3e6ea;color:#868e96;transition:.2s}
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
    .aproute-note{font-size:.72rem;color:#6c757d;font-style:italic;margin-top:.25rem;max-width:148px}

    /* ===== Pilihan Keputusan (segmented, proporsional) ===== */
    .decision-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
    .decision-opt{display:flex;align-items:center;justify-content:center;gap:.5rem;margin:0;
        padding:.8rem 1rem;border:1.5px solid #dee2e6;border-radius:.6rem;background:#fff;
        font-weight:600;color:#6c757d;cursor:pointer;transition:.15s;user-select:none}
    .decision-opt .bi{font-size:1.2rem;line-height:1}
    .decision-opt:hover{border-color:#adb5bd;background:#f8f9fa}
    .btn-check:focus-visible+.decision-opt{box-shadow:0 0 0 .2rem rgba(13,110,253,.25)}
    .btn-check:checked+.decision-opt.opt-approve{border-color:#198754;background:#198754;color:#fff;box-shadow:0 3px 10px rgba(25,135,84,.25)}
    .btn-check:checked+.decision-opt.opt-reject{border-color:#dc3545;background:#dc3545;color:#fff;box-shadow:0 3px 10px rgba(220,53,69,.25)}
</style>

{{-- ===== Header ===== --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('inbox.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Inbox
        </a>
        <strong>Detail Task</strong>
    </div>
    <span class="badge bg-{{ $sc }} fs-6">{{ $req->request_status }}</span>
</div>

{{-- ===== 1. Data Dokumen ===== --}}
@php
    $payloadArr = is_array($req->payload_json) ? $req->payload_json
        : json_decode($req->payload_json ?? '{}', true);
@endphp
@include('partials._context_renderer', [
    'payloadJson'   => $payloadArr ?? [],
    'contextJson'   => $contextJson,
    'formSchema'    => optional($req->documentType)->form_schema ?? [],
    'docTypeName'   => optional($req->documentType)->doc_name,
    'sourceBaseUrl' => optional($req->sourceApp)->base_url,
    'showRawJson'   => false,
])

{{-- ===== 2. Keputusan (Step) ===== --}}
<div class="card shadow-sm border-{{ $isOpen ? 'primary' : $dColor }} mb-3">
    <div class="card-header card-collapsible bg-{{ $isOpen ? 'primary' : $dColor }} text-white d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-step" aria-expanded="true" aria-controls="c-step">
        <span><i class="bi bi-check2-square"></i> Step: @nodeLabel(optional($task->flowStep)->step_name)</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div id="c-step" class="collapse show">
        <div class="card-body">
        @if($isOpen)
            <form method="POST" action="{{ route('inbox.act', $task->idtbltask) }}"
                  data-confirm="Yakin dengan keputusan ini?">
                @csrf
                @include('partials._errors')

                @if($task->due_at)
                <div class="alert alert-{{ $task->due_at->isPast() ? 'danger' : 'info' }} small py-2">
                    <i class="bi bi-alarm"></i> Due: <strong>{{ $task->due_at->format('d M Y H:i') }}</strong>
                    @if($task->due_at->isPast()) — <span class="text-danger fw-bold">OVERDUE</span> @endif
                </div>
                @endif

                @if(optional($task->flowStep)->instruction)
                <div class="alert alert-light small py-2 mb-3">
                    <strong>Instruksi:</strong><br>{{ $task->flowStep->instruction }}
                </div>
                @endif

                {{-- 0. Edit Data (hanya field yang di-whitelist untuk node ini) --}}
                @if(!empty($editableFields))
                <div class="card border-warning mb-3">
                    <div class="card-header bg-warning bg-opacity-10 small fw-semibold py-2">
                        <i class="bi bi-pencil-square text-warning"></i> Edit Data (boleh diubah di step ini)
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($editableFields as $ef)
                                @php $val = old('edits.'.$ef['path'], $ef['value']); @endphp
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">
                                        {{ $ef['label'] }}
                                        <code class="text-muted" style="font-size:.7rem">{{ $ef['path'] }}</code>
                                    </label>
                                    @if($ef['type'] === 'textarea')
                                        <textarea name="edits[{{ $ef['path'] }}]" class="form-control" rows="2">{{ $val }}</textarea>
                                    @elseif(in_array($ef['type'], ['number','currency']))
                                        <input type="number" step="any" name="edits[{{ $ef['path'] }}]" class="form-control" value="{{ $val }}">
                                    @elseif($ef['type'] === 'date')
                                        <input type="date" name="edits[{{ $ef['path'] }}]" class="form-control" value="{{ $val }}">
                                    @else
                                        <input type="text" name="edits[{{ $ef['path'] }}]" class="form-control" value="{{ $val }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i>
                            Perubahan dicatat di audit & ikut terkirim ke aplikasi asal saat proses selesai.
                        </small>
                    </div>
                </div>
                @endif

                {{-- 1. Keputusan --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold mb-2">Keputusan <span class="text-danger">*</span></label>
                    <div class="decision-grid">
                        @foreach(['APPROVE' => ['approve','check-circle-fill','Setujui'],
                                   'REJECT'  => ['reject', 'x-circle-fill',   'Tolak']] as $code => [$variant, $icon, $label])
                            <input type="radio" class="btn-check" name="decision_code" value="{{ $code }}"
                                   id="dec_{{ $code }}" autocomplete="off"
                                   {{ old('decision_code') === $code ? 'checked' : '' }} required>
                            <label class="decision-opt opt-{{ $variant }}" for="dec_{{ $code }}">
                                <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- 2. Catatan --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold mb-1" for="decision_note">
                        Catatan
                        <span class="text-danger" id="note-req" style="display:none">*</span>
                        <span class="fw-normal text-muted small" id="note-hint">(opsional)</span>
                    </label>
                    <textarea name="decision_note" id="decision_note" class="form-control" rows="3"
                              placeholder="Tambahkan catatan untuk keputusan ini…">{{ old('decision_note') }}</textarea>
                </div>

                {{-- 3. Tombol (adaptif terhadap pilihan) --}}
                <button type="submit" id="dec-submit" class="btn btn-secondary w-100" disabled>
                    <i class="bi bi-hand-index"></i> Pilih keputusan dulu
                </button>
            </form>
            <script>
            (function(){
                var note = document.getElementById('decision_note');
                var req  = document.getElementById('note-req');
                var hint = document.getElementById('note-hint');
                var btn  = document.getElementById('dec-submit');
                var apr  = document.getElementById('dec_APPROVE');
                var rej  = document.getElementById('dec_REJECT');
                function sync(){
                    var approving = apr && apr.checked;
                    var rejecting = rej && rej.checked;
                    if (note) note.required      = !!rejecting;
                    if (req)  req.style.display  = rejecting ? '' : 'none';
                    if (hint) hint.style.display = rejecting ? 'none' : '';
                    if (!btn) return;
                    if (approving){
                        btn.disabled = false; btn.className = 'btn btn-success w-100';
                        btn.innerHTML = '<i class="bi bi-check-circle"></i> Setujui &amp; Kirim';
                    } else if (rejecting){
                        btn.disabled = false; btn.className = 'btn btn-danger w-100';
                        btn.innerHTML = '<i class="bi bi-x-circle"></i> Tolak &amp; Kirim';
                    } else {
                        btn.disabled = true; btn.className = 'btn btn-secondary w-100';
                        btn.innerHTML = '<i class="bi bi-hand-index"></i> Pilih keputusan dulu';
                    }
                }
                document.querySelectorAll('input[name="decision_code"]').forEach(function(r){ r.addEventListener('change', sync); });
                sync();
            })();
            </script>
        @else
            {{-- Task sudah selesai → ringkasan keputusan --}}
            <div class="text-center mb-3">
                <span class="badge bg-{{ $dColor }} fs-6">
                    <i class="bi bi-{{ $dIcon }}"></i> {{ $dLabel }}
                </span>
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-3">Diputuskan oleh</dt>
                <dd class="col-sm-9">
                    {{ optional($task->completedBy)->full_name ?? optional($task->completedBy)->user_ref ?? '—' }}
                </dd>
                <dt class="col-sm-3">Tanggal</dt>
                <dd class="col-sm-9">{{ $task->completed_at?->format('d M Y H:i') ?? '—' }}</dd>
                <dt class="col-sm-3">Catatan</dt>
                <dd class="col-sm-9">
                    @if($task->decision_note)
                        <div class="border rounded p-2 bg-light">{{ $task->decision_note }}</div>
                    @else
                        <span class="text-muted fst-italic">Tidak ada catatan</span>
                    @endif
                </dd>
            </dl>
        @endif
        </div>
    </div>
</div>

{{-- ===== 3. Alur Persetujuan ===== --}}
@if(!empty($approvalRoute))
<div class="card shadow-sm mb-3">
    <div class="card-header card-collapsible d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-alur" aria-expanded="true" aria-controls="c-alur">
        <span class="fw-semibold"><i class="bi bi-diagram-3"></i> Alur Persetujuan</span>
        <span class="small text-muted">
            <span class="me-2"><i class="bi bi-circle-fill text-success" style="font-size:.6rem"></i> Sudah</span>
            <span class="me-2"><i class="bi bi-circle-fill text-primary" style="font-size:.6rem"></i> Sekarang</span>
            <span class="me-2"><i class="bi bi-circle text-secondary" style="font-size:.6rem"></i> Akan</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </div>
    <div id="c-alur" class="collapse show">
        <div class="card-body py-2">
            <div class="aproute">
                @foreach($approvalRoute as $i => $step)
                    @php
                        $node  = $step['node'];
                        $t     = $step['task'];
                        $state = $step['state'];
                        $cfg = [
                            'done'     => ['is-done',     'check-lg',                'Disetujui',    'success'],
                            'current'  => ['is-current',  'arrow-right',             'Sekarang',     'primary'],
                            'future'   => ['',            '',                        'Akan',         'secondary'],
                            'rejected' => ['is-rejected', 'x-lg',                    'Ditolak',      'danger'],
                            'returned' => ['is-returned', 'arrow-counterclockwise',  'Dikembalikan', 'warning'],
                        ];
                        [$dotCls,$icon,$stateLbl,$txtColor] = $cfg[$state] ?? ['','','—','secondary'];
                    @endphp
                    @if(!$loop->first)
                        @php $prev = $approvalRoute[$i-1]['state']; $lineDone = in_array($prev, ['done','rejected','returned']); @endphp
                        <div class="aproute-step"><div class="aproute-line {{ $lineDone ? 'is-done' : '' }}"></div></div>
                    @endif
                    <div class="aproute-step">
                        <div class="aproute-node">
                            {{-- Angka urut selalu di tengah; status via warna + label di bawah --}}
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
</div>
@endif

{{-- ===== 4. Riwayat Keputusan (SHOWN default) ===== --}}
@if($history->isNotEmpty())
<div class="card shadow-sm mb-3">
    <div class="card-header card-collapsible small d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-history" aria-expanded="true" aria-controls="c-history">
        <span><i class="bi bi-clock-history"></i> Riwayat Keputusan</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div id="c-history" class="collapse show">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light small"><tr>
                        <th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Catatan</th>
                    </tr></thead>
                    <tbody class="small">
                    @foreach($history as $h)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $h->created_at?->format('d/m H:i') }}</td>
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
</div>
@endif

{{-- ===== 5. Informasi Request ===== --}}
<div class="card shadow-sm mb-3">
    <div class="card-header card-collapsible small d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-info" aria-expanded="false" aria-controls="c-info">
        <span><i class="bi bi-file-earmark-text"></i> Informasi Request</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div id="c-info" class="collapse">
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-4">No Request</dt>
                <dd class="col-sm-8"><code>{{ $req->source_request_no ?? '-' }}</code></dd>
                <dt class="col-sm-4">Judul</dt>
                <dd class="col-sm-8">{{ $req->title }}</dd>
                <dt class="col-sm-4">Source App</dt>
                <dd class="col-sm-8">{{ optional($req->sourceApp)->app_code }}</dd>
                <dt class="col-sm-4">Tipe Dokumen</dt>
                <dd class="col-sm-8">{{ optional($req->documentType)->doc_name }}</dd>
                <dt class="col-sm-4">Pemohon</dt>
                <dd class="col-sm-8">{{ $req->requester_name }} <span class="text-muted">({{ $req->requester_ref }})</span></dd>
                <dt class="col-sm-4">Org</dt>
                <dd class="col-sm-8">{{ $req->requester_org_name ?? '-' }}</dd>
                @if($req->amount)
                <dt class="col-sm-4">Nilai</dt>
                <dd class="col-sm-8">{{ $req->currency_code }} {{ number_format($req->amount, 2) }}</dd>
                @endif
                <dt class="col-sm-4">Prioritas</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-{{ match($req->priority) { 'URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary',default=>'info' } }}">
                        {{ $req->priority }}
                    </span>
                </dd>
                <dt class="col-sm-4">Dibuat</dt>
                <dd class="col-sm-8">{{ $req->created_at?->format('d M Y H:i') }}</dd>
            </dl>
        </div>
    </div>
</div>

{{-- ===== 6. Jejak Perjalanan Request (HIDDEN default) ===== --}}
@if($req->routeLogs->isNotEmpty())
<div class="card shadow-sm mb-3">
    <div class="card-header card-collapsible small d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-route" aria-expanded="false" aria-controls="c-route">
        <span><i class="bi bi-map"></i> Jejak Perjalanan Request</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div id="c-route" class="collapse">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light small"><tr>
                        <th>Waktu</th><th>Event</th><th>Node</th><th>Pesan</th>
                    </tr></thead>
                    <tbody class="small">
                    @foreach($req->routeLogs as $log)
                        <tr>
                            <td class="text-muted text-nowrap">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                            <td><code class="small">{{ $log->route_event }}</code></td>
                            <td>@nodeLabel(optional($log->flowStep)->node_code)</td>
                            <td class="text-muted">{{ $log->message }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

@if(! $isOpen)
<a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Kembali
</a>
@endif
@endsection
