@extends('layouts.app')
@section('title', 'Detail Request')

@section('content')
@php
$req = $approval_request;
$sc = ['SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
       'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'];
@endphp

<div class="mb-3">
    <a href="{{ route('monitoring.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="row g-3">
    {{-- Info Request --}}
    <div class="col-md-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header small d-flex justify-content-between">
                <span><i class="bi bi-file-earmark-text"></i> Detail Request</span>
                <span class="badge bg-{{ $sc[$req->request_status] ?? 'secondary' }} fs-6">{{ $req->request_status }}</span>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">No Request</dt><dd class="col-7"><code>{{ $req->source_request_no }}</code></dd>
                    <dt class="col-5">Judul</dt><dd class="col-7">{{ $req->title }}</dd>
                    <dt class="col-5">Source App</dt><dd class="col-7">{{ optional($req->sourceApp)->app_code }}</dd>
                    <dt class="col-5">Tipe Dok</dt><dd class="col-7">{{ optional($req->documentType)->doc_name }}</dd>
                    <dt class="col-5">Pemohon</dt><dd class="col-7">{{ $req->requester_name }}<br><code class="small">{{ $req->requester_ref }}</code></dd>
                    <dt class="col-5">Org</dt><dd class="col-7">{{ $req->requester_org_name ?? '—' }}</dd>
                    @if($req->amount)<dt class="col-5">Nilai</dt><dd class="col-7">{{ $req->currency_code }} {{ number_format($req->amount, 2) }}</dd>@endif
                    <dt class="col-5">Prioritas</dt>
                    <dd class="col-7">
                        @php $prioBadge = ['URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary'][$req->priority] ?? 'info'; @endphp
                        <span class="badge bg-{{ $prioBadge }}">{{ $req->priority }}</span>
                    </dd>
                    <dt class="col-5">Flow</dt><dd class="col-7">{{ optional(optional(optional($req->processInstance)->flowVersion)->flowDefinition)->flow_name ?? '—' }}</dd>
                    <dt class="col-5">Version</dt><dd class="col-7">v{{ optional(optional($req->processInstance)->flowVersion)->version_no ?? '—' }}</dd>
                    <dt class="col-5">Step Kini</dt><dd class="col-7">@nodeLabel(optional(optional($req->processInstance)->flowStepCurrent)->step_name)</dd>
                    <dt class="col-5">Dibuat</dt><dd class="col-7">{{ $req->created_at?->format('d M Y H:i') }}</dd>
                    <dt class="col-5">Diperbarui</dt><dd class="col-7">{{ $req->updated_at?->format('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        {{-- Tasks --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header small"><i class="bi bi-list-task"></i> Daftar Task</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Step</th><th>Assignee</th><th>Status</th><th>Selesai</th></tr></thead>
                    <tbody>
                    @forelse($tasks as $t)
                        @php
                            $taskBadge = ['APPROVED'=>'success','REJECTED'=>'danger','OPEN'=>'warning',
                                          'CANCELLED'=>'secondary','RETURNED'=>'warning','CLAIMED'=>'info',
                                          'EXPIRED'=>'dark','SKIPPED'=>'secondary'][$t->task_status] ?? 'info';
                        @endphp
                        <tr>
                            <td>@nodeLabel(optional($t->flowStep)->step_name)</td>
                            <td>
                                @php $picUser = $t->completedBy ?? $t->claimedBy ?? $t->assignedUser ?? optional(optional($t->candidates)->first())->user; @endphp
                                @if($picUser){{ $picUser->full_name ? $picUser->full_name.' - '.$picUser->user_ref : $picUser->user_ref }}@else<span class="text-muted">—</span>@endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $taskBadge }}">
                                    {{ $t->task_status }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $t->completed_at?->format('d/m H:i') ?? '—' }}</td>
                        </tr>
                    @empty <tr><td colspan="4" class="text-muted text-center">Belum ada task.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- RIGHT: Timeline & Route Log --}}
    <div class="col-md-7">
        {{-- Data Dokumen — ditampilkan dinamis berdasarkan form_schema --}}
        @php $ctxArr = is_array($req->context_json) ? $req->context_json : json_decode($req->context_json ?? '{}', true); @endphp
        @php $payArr = is_array($req->payload_json) ? $req->payload_json : json_decode($req->payload_json ?? '{}', true); @endphp
        @include('partials._context_renderer', [
            'payloadJson'   => $payArr,
            'contextJson'   => $ctxArr,
            'formSchema'    => optional($req->documentType)->form_schema ?? [],
            'docTypeName'   => optional($req->documentType)->doc_name,
            'sourceBaseUrl' => optional($req->sourceApp)->base_url,
        ])

        {{-- Action Log (keputusan) --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header small"><i class="bi bi-clock-history"></i> Riwayat Keputusan</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @forelse($actionLogs as $al)
                        @php
                            $alBadge = in_array($al->action_code, ['APPROVE','AUTO_APPROVE']) ? 'success'
                                : ($al->action_code === 'REJECT' ? 'danger'
                                : ($al->action_code === 'RETURN' ? 'warning'
                                : ($al->action_code === 'CANCEL' ? 'secondary' : 'info')));
                        @endphp
                        <tr>
                            <td class="text-nowrap text-muted">{{ $al->created_at?->format('d/m H:i') }}</td>
                            <td>{{ $al->actor_name ? $al->actor_name.' - '.$al->actor_ref : $al->actor_ref }}</td>
                            <td><span class="badge bg-{{ $alBadge }}">{{ $al->action_code }}</span></td>
                            <td class="text-muted">{{ Str::limit($al->action_note, 60) }}</td>
                        </tr>
                    @empty <tr><td colspan="4" class="text-muted text-center py-3">Belum ada keputusan.</td></tr> @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Route Log (jalur engine) --}}
        <div class="card shadow-sm">
            <div class="card-header small">
                <i class="bi bi-map"></i> Route Log (Jejak Engine)
                <button class="btn btn-xs btn-outline-secondary btn-sm float-end"
                        data-bs-toggle="collapse" data-bs-target="#routeLog">Toggle</button>
            </div>
            <div class="collapse" id="routeLog">
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light sticky-top"><tr><th>Waktu</th><th>Event</th><th>Node</th><th>Pesan</th></tr></thead>
                        <tbody>
                        @forelse($req->routeLogs as $log)
                            <tr>
                                <td class="text-nowrap text-muted">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                                <td><code class="small">{{ $log->route_event }}</code></td>
                                <td>@nodeLabel(optional($log->flowStep)->node_code)</td>
                                <td class="text-muted">{{ $log->message }}</td>
                            </tr>
                        @empty <tr><td colspan="4" class="text-muted text-center py-3">Belum ada route log.</td></tr> @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
