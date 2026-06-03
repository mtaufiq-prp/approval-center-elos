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
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('inbox.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Inbox
        </a>
        <strong>Detail Task</strong> 
    </div> 
    <span class="badge bg-{{ $sc }} fs-6">{{ $req->request_status }}</span>
</div>

{{-- ALUR PERSETUJUAN (routing) --}}
@if(!empty($approvalRoute)) 
@php
    $routeCfg = [ 
        'done'     => ['success',  'check-circle-fill',       'Disetujui'],
        'current'  => ['primary',  'arrow-right-circle-fill', 'Sekarang'],
        'future'   => ['secondary','circle',                  'Akan'],
        'rejected' => ['danger',   'x-circle-fill',           'Ditolak'],
        'returned' => ['warning',  'arrow-counterclockwise',  'Dikembalikan'],
    ];
@endphp 
<style>
    .approval-route{display:flex;flex-wrap:nowrap;overflow-x:auto;align-items:flex-start;justify-content:center;gap:0;padding:1rem .25rem 1.25rem}
    .approval-route::-webkit-scrollbar{height:7px}
    .approval-route::-webkit-scrollbar-thumb{background:#cfd4da;border-radius:4px}
    .approval-route .ar-step{display:flex;align-items:flex-start;flex:0 0 auto}
    .approval-route .ar-node{display:flex;flex-direction:column;align-items:center;text-align:center;width:158px;padding:0 .25rem}
    .approval-route .ar-circle{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.16);transition:transform .15s ease;position:relative}
    .approval-route .ar-circle:hover{transform:scale(1.08)}
    .approval-route .ar-circle.is-current{animation:arPulse 1.6s infinite}
    @keyframes arPulse{0%{box-shadow:0 0 0 0 rgba(13,110,253,.5)}70%{box-shadow:0 0 0 14px rgba(13,110,253,0)}100%{box-shadow:0 0 0 0 rgba(13,110,253,0)}}
    .approval-route .ar-idx{position:absolute;top:-6px;right:-6px;background:#fff;color:#495057;border:1px solid #dee2e6;border-radius:50%;width:20px;height:20px;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .approval-route .ar-line{height:4px;flex:1 1 auto;min-width:30px;border-radius:3px;margin-top:28px;opacity:.85}
    .approval-route .ar-name{font-weight:700;font-size:.82rem;margin-top:.55rem;line-height:1.15;color:#343a40}
    .approval-route .ar-meta{font-size:.7rem;line-height:1.25;color:#6c757d}
    .approval-route .ar-chip{display:inline-block;font-size:.68rem;line-height:1.25;background:#f1f3f5;color:#495057;border-radius:11px;padding:1px 9px;margin-top:3px;max-width:148px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .approval-route .ar-chip.is-current{background:#e7f1ff;color:#0d6efd;font-weight:600}
    .ar-legend .dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:3px;vertical-align:middle}
    .card-collapsible{cursor:pointer;user-select:none}
    .card-collapsible .bi-chevron-down{transition:transform .25s ease}
    .card-collapsible[aria-expanded="false"] .bi-chevron-down{transform:rotate(-90deg)}
</style>
<div class="card shadow-sm mb-3">
    <div class="card-header card-collapsible d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#c-alur" aria-expanded="true" aria-controls="c-alur">
        <span class="fw-semibold"><i class="bi bi-diagram-3"></i> Alur Persetujuan</span>
        <span class="small ar-legend text-muted">
            <span class="me-2"><span class="dot bg-success"></span> Sudah</span>
            <span class="me-2"><span class="dot bg-primary"></span> Sekarang</span>
            <span class="me-2"><span class="dot bg-secondary"></span> Akan</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </div>
    <div id="c-alur" class="collapse show">
    <div class="card-body py-2">
        <div class="approval-route">
            @foreach($approvalRoute as $i => $step)
                @php
                    $node  = $step['node'];
                    $t     = $step['task'];
                    [$color,$icon,$label] = $routeCfg[$step['state']] ?? ['secondary','circle','—'];
                @endphp
                @if(!$loop->first)
                    @php
                        $prevState = $approvalRoute[$i-1]['state'];
                        $lineColor = in_array($prevState, ['done','rejected','returned']) ? $routeCfg[$prevState][0] : 'secondary';
                    @endphp
                    <div class="ar-step"><div class="ar-line bg-{{ $lineColor }}"></div></div>
                @endif
                <div class="ar-step">
                    <div class="ar-node">
                        <div class="ar-circle bg-{{ $color }} {{ $step['state']==='current' ? 'is-current' : '' }}">
                            <i class="bi bi-{{ $icon }} fs-4"></i>
                            <span class="ar-idx">{{ $i + 1 }}</span>
                        </div>
                        <div class="ar-name">{{ $node->step_name }}</div>
                        <span class="badge bg-{{ $color }} mt-1" style="font-size:.7rem">{{ $label }}</span>
                        @if($t)
                            {{-- Sudah diputuskan --}}
                            <div class="ar-meta mt-1">
                                <code>{{ optional($t->completedBy)->user_ref }}</code>
                            </div>
                            <div class="ar-meta">{{ \Illuminate\Support\Str::limit(optional($t->completedBy)->full_name, 18) }}</div>
                            <div class="ar-meta">{{ $t->completed_at?->format('d/m H:i') }}</div>
                            @if($t->decision_note)
                                <span class="ar-chip" title="{{ $t->decision_note }}">
                                    "{{ \Illuminate\Support\Str::limit($t->decision_note, 18) }}"
                                </span>
                            @endif
                        @elseif(!empty($step['pending']))
                            {{-- Sedang / akan menunggu approver --}}
                            <div class="ar-meta mt-1 fw-semibold">
                                {{ $step['state'] === 'current' ? 'Pending di:' : 'Calon:' }}
                            </div>
                            @foreach(array_slice($step['pending'], 0, 3) as $p)
                                <span class="ar-chip {{ $step['state'] === 'current' ? 'is-current' : '' }}"
                                      title="{{ $p['ref'] }} — {{ $p['name'] }}">
                                    {{ \Illuminate\Support\Str::limit($p['name'] ?: $p['ref'], 18) }}
                                </span>
                            @endforeach
                            @if(count($step['pending']) > 3)
                                <span class="ar-chip">+{{ count($step['pending']) - 3 }} lainnya</span>
                            @endif
                        @else
                            <div class="ar-meta mt-1 fst-italic">—</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    </div>
</div>
@endif 

@php
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
{{-- Layout full-width (kolom kanan dihapus) --}}

        {{-- Informasi Request (HIDDEN default) --}}
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

        {{-- Data Dokumen (dirender oleh _context_renderer; cards di dalamnya milik partial) --}}
        @php
            $payloadArr = is_array($req->payload_json) ? $req->payload_json
                : json_decode($req->payload_json ?? '{}', true);
        @endphp
        @include('partials._context_renderer', [
            'payloadJson' => $payloadArr ?? [],
            'contextJson' => $contextJson,
            'formSchema'  => optional($req->documentType)->form_schema ?? [],
            'docTypeName' => optional($req->documentType)->doc_name,
        ])

        {{-- STEP: form keputusan / ringkasan (dipindah dari kanan ke sini, SHOWN default) --}}
        <div class="card shadow-sm border-{{ $isOpen ? 'primary' : $dColor }} mb-3">
            <div class="card-header card-collapsible bg-{{ $isOpen ? 'primary' : $dColor }} text-white d-flex justify-content-between align-items-center"
                 role="button" data-bs-toggle="collapse" data-bs-target="#c-step" aria-expanded="true" aria-controls="c-step">
                <span><i class="bi bi-check2-square"></i> Step: {{ optional($task->flowStep)->step_name }}</span>
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

                        {{-- 1. Keputusan (horizontal) --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Keputusan <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2">
                                @foreach(['APPROVE' => ['success','check-circle','Setujui'],
                                           'REJECT'  => ['danger', 'x-circle',   'Tolak'],
                                           'RETURN'  => ['warning','arrow-counterclockwise','Kembalikan']] as $code => [$color, $icon, $label])
                                <div class="form-check border rounded p-2 flex-fill {{ old('decision_code') === $code ? "border-$color bg-$color bg-opacity-10" : '' }}">
                                    <input class="form-check-input" type="radio"
                                           name="decision_code" value="{{ $code }}"
                                           id="dec_{{ $code }}"
                                           {{ old('decision_code') === $code ? 'checked' : '' }} required>
                                    <label class="form-check-label fw-semibold text-{{ $color }}" for="dec_{{ $code }}">
                                        <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div> 

                        {{-- 2. Catatan --}}
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="decision_note" class="form-control" rows="4"
                                      placeholder="Wajib diisi jika menolak / mengembalikan...">{{ old('decision_note') }}</textarea>
                        </div>

                        {{-- 3. Tombol --}}
                        <button class="btn btn-primary w-100">
                            <i class="bi bi-send"></i> Kirim Keputusan
                        </button>
                    </form>
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
                            <code>{{ optional($task->completedBy)->user_ref ?? '—' }}</code>
                            {{ optional($task->completedBy)->full_name }}
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

        {{-- Jejak Perjalanan Request (HIDDEN default) --}}
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
                                    <td>{{ optional($log->flowStep)->node_code ?? '—' }}</td>
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

        {{-- Riwayat Keputusan (SHOWN default) --}}
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
                                    <td><code>{{ $h->actor_ref }}</code></td>
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

        @if(! $isOpen)
        <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        @endif
@endsection
