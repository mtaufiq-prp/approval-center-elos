{{--
    _context_renderer.blade.php

    Variables:
      $payloadJson  : array  — payload_json lengkap (nested: header[], detail[], dll)
      $contextJson  : array  — context_json flat (fallback jika payload kosong)
      $formSchema   : array|null
      $docTypeName  : string
--}}
@php
    // Gunakan payload_json jika ada, fallback ke context_json
    $payload    = $payloadJson ?? $contextJson ?? [];
    $ctx        = $contextJson ?? [];
    $schema     = $formSchema  ?? [];
    $hasSchema  = !empty($schema);
    // base_url master source app (input admin di hub) → utk menyusun link relatif (mis. lampiran)
    $baseUrl    = $sourceBaseUrl ?? '';

    /**
     * Resolve nilai dari nested path seperti "header.customer_name"
     * Jika field adalah array (mis. header adalah array of objects),
     * ambil elemen pertama secara otomatis.
     */
    function resolveField(array $data, string $field): mixed {
        if ($field === '') return null;
        $parts = explode('.', $field);
        $current = $data;
        foreach ($parts as $part) {
            if (is_array($current)) {
                // Jika array of objects (indexed array), ambil elemen pertama
                if (isset($current[0]) && is_array($current[0])) {
                    $current = $current[0];
                }
                $current = $current[$part] ?? null;
            } else {
                return null;
            }
        }
        return $current;
    }

    // Kumpulkan semua key yang sudah ada di schema (untuk "data tambahan")
    $schemaFields = collect($schema)->pluck('field')->filter()->toArray();
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
            <i class="bi bi-file-earmark-text"></i> Data Dokumen
            @if(!empty($docTypeName))
                <span class="badge bg-primary ms-1">{{ $docTypeName }}</span>
            @endif
        </span>
        @if($hasSchema && ($showRawJson ?? true))
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary active" id="btn-vf" onclick="switchView('form')">
                <i class="bi bi-layout-text-window"></i> Form
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btn-vr" onclick="switchView('raw')">
                <i class="bi bi-code-slash"></i> Raw JSON
            </button>
        </div>
        @endif
    </div>

    <div class="collapse show" id="ctx-body">

        {{-- ── FORM VIEW ─────────────────────────────────────────── --}}
        @if($hasSchema)
        <div class="card-body p-3" id="ctx-form-view">
            <div class="row g-3">
            @foreach($schema as $fd)
                @php
                    $type    = $fd['type']    ?? 'text';
                    $field   = $fd['field']   ?? '';
                    $label   = $fd['label']   ?? $field;
                    $width   = $fd['width']   ?? 'half';
                    $colCls  = match($width) {
                        'full'  => 'col-12',
                        'third' => 'col-md-4',
                        default => 'col-md-6',
                    };
                    // Resolve value: coba payload dulu, fallback context flat
                    $rawVal  = $field ? resolveField($payload, $field) : null;
                    if ($rawVal === null && $field) {
                        // Fallback: coba langsung dari ctx flat (tanpa prefix)
                        $lastKey = last(explode('.', $field));
                        $rawVal  = $ctx[$lastKey] ?? $ctx[$field] ?? null;
                    }
                    $default = $fd['default'] ?? '—';
                @endphp

                @if($type === 'separator')
                    <div class="col-12 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-primary small text-uppercase" style="letter-spacing:.06em;white-space:nowrap">
                                {{ $label ?: 'Detail' }}
                            </span>
                            <hr class="flex-fill m-0">
                        </div>
                    </div>

                @elseif($type === 'table')
                    @php
                        // Untuk table: ambil seluruh array dari payload (bukan first element)
                        $tableData = $field ? ($payload[explode('.', $field)[0]] ?? []) : [];
                        if (!is_array($tableData) || (isset($tableData[0]) && !is_array($tableData[0]))) {
                            $tableData = [];
                        }
                        // Jika path lebih dari 1 level mis. detail.items
                        if (str_contains($field, '.')) {
                            $tableData = resolveField($payload, $field) ?? [];
                            if (!is_array($tableData) || empty($tableData)) {
                                $topKey    = explode('.', $field)[0];
                                $tableData = $payload[$topKey] ?? [];
                            }
                        }
                        $columns   = $fd['columns']    ?? (isset($tableData[0]) ? array_keys($tableData[0]) : []);
                        $colLabels = $fd['col_labels'] ?? $columns;
                    @endphp
                    <div class="{{ $colCls }}">
                        <div class="small text-muted mb-1">{{ $label }}</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:32px">#</th>
                                        @foreach($colLabels as $cl)<th>{{ $cl }}</th>@endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tableData as $ri => $row)
                                        <tr>
                                            <td class="text-center text-muted">{{ $ri + 1 }}</td>
                                            @foreach($columns as $col)
                                                @php $cv = is_array($row) ? ($row[$col] ?? '—') : '—'; @endphp
                                                <td>
                                                    @if(is_numeric($cv) && in_array(strtolower($col), ['value_retur','value_retur_ori','value_potong_budget','harga','nilai']))
                                                        {{ number_format((float)$cv, 0, ',', '.') }}
                                                    @else
                                                        {{ $cv }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ count($columns)+1 }}" class="text-center text-muted">Tidak ada data</td></tr>
                                    @endforelse
                                </tbody>
                                @if(count($tableData) > 0)
                                @php
                                    // Hanya total kolom kuantitas/nilai — JANGAN total identifier (kode_barang dll).
                                    // Schema boleh override eksplisit via "total_columns": [...].
                                    $explicitTotals = $fd['total_columns'] ?? null;
                                    if (is_array($explicitTotals)) {
                                        $numCols = array_values(array_intersect($columns, $explicitTotals));
                                    } else {
                                        // Kuantitas (qty) TIDAK ditotal — unit bisa beda antar item. Hanya kolom nilai uang.
                                        $sumable = ['total','nilai','harga','value','amount','subtotal'];
                                        $numCols = array_values(array_filter($columns, fn($c) =>
                                            isset($tableData[0][$c]) && is_numeric($tableData[0][$c])
                                            && \Illuminate\Support\Str::contains(strtolower($c), $sumable)
                                        ));
                                    }
                                @endphp
                                @if(count($numCols) > 0)
                                <tfoot class="table-light fw-semibold">
                                    <tr>
                                        <td colspan="{{ count($columns) - count($numCols) + 1 }}" class="text-end small text-muted">Total</td>
                                        @foreach($columns as $col)
                                            @if(in_array($col, $numCols))
                                                <td>{{ number_format(array_sum(array_column($tableData, $col)), 0, ',', '.') }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                </tfoot>
                                @endif
                                @endif
                            </table>
                        </div>
                    </div>

                @else
                    <div class="{{ $colCls }}">
                        <div class="small text-muted mb-1">{{ $label }}</div>
                        <div>
                        @if($rawVal === null || $rawVal === '')
                            <span class="text-muted small">{{ $default }}</span>
                        @elseif($type === 'currency')
                            @php $prefix = $fd['prefix'] ?? 'Rp '; @endphp
                            <span class="fw-semibold text-success">{{ $prefix . number_format((float)$rawVal, 0, ',', '.') }}</span>
                        @elseif($type === 'number')
                            <span class="fw-semibold">{{ number_format((float)$rawVal, 0, ',', '.') }}</span>
                        @elseif($type === 'badge')
                            @php $bc = ($fd['colors'] ?? [])[$rawVal] ?? 'secondary'; @endphp
                            <span class="badge bg-{{ $bc }} px-3 py-1 fs-6">{{ $rawVal }}</span>
                        @elseif($type === 'date')
                            @php try { echo \Carbon\Carbon::parse($rawVal)->translatedFormat('d F Y'); } catch(\Exception $e) { echo $rawVal; } @endphp
                        @elseif($type === 'datetime')
                            @php try { echo \Carbon\Carbon::parse($rawVal)->translatedFormat('d F Y, H:i'); } catch(\Exception $e) { echo $rawVal; } @endphp
                        @elseif($type === 'textarea')
                            <div class="bg-light border rounded p-2 small" style="white-space:pre-wrap;min-height:40px">{{ $rawVal }}</div>
                        @elseif($type === 'image')
                            <a href="{{ $rawVal }}" target="_blank">
                                <span class="badge bg-info"><i class="bi bi-image"></i> {{ Str::limit($rawVal, 40) }}</span>
                            </a>
                        @elseif($type === 'list' && is_array($rawVal))
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($rawVal as $item)
                                    <span class="badge bg-light text-dark border">{{ $item }}</span>
                                @endforeach
                            </div>
                        @elseif($type === 'links' && is_array($rawVal))
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($rawVal as $item)
                                    @php
                                        if (is_array($item)) {
                                            $lnama = $item['nama'] ?? $item['path'] ?? $item['url'] ?? 'file';
                                            if (!empty($item['url'])) {
                                                $lurl = $item['url'];                    // absolut: apa adanya
                                            } elseif (!empty($item['path'])) {
                                                // relatif: gabung dgn base_url master source app
                                                $lurl = rtrim($baseUrl, '/').'/'.ltrim($item['path'], '/');
                                            } else { $lurl = '#'; }
                                        } else { $lnama = (string)$item; $lurl = (string)$item; }
                                    @endphp
                                    <a href="{{ $lurl }}" target="_blank" class="badge bg-primary text-decoration-none">
                                        <i class="bi bi-paperclip"></i> {{ $lnama }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            {{ is_array($rawVal) ? json_encode($rawVal, JSON_UNESCAPED_UNICODE) : $rawVal }}
                        @endif
                        </div>
                    </div>
                @endif
            @endforeach
            </div>

            {{-- Data tambahan (field di payload tapi tidak di schema) --}}
            @php
                // Cek flat ctx untuk field yang tidak ada di schema
                $extras = array_diff_key($ctx, array_flip($schemaFields));
                // Hapus field yang sudah ada lewat prefix match
                foreach ($schemaFields as $sf) {
                    $last = last(explode('.', $sf));
                    unset($extras[$last]);
                }
                $extras = array_filter($extras, fn($v) => !is_array($v) && $v !== null && $v !== '');
            @endphp
            @if(!empty($extras))
            <div class="mt-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="small text-muted text-uppercase" style="font-size:10px;letter-spacing:.05em">Data Tambahan</span>
                    <hr class="flex-fill m-0">
                </div>
                <div class="row g-2">
                @foreach($extras as $key => $val)
                    <div class="col-md-6">
                        <span class="small text-muted">{{ $key }}</span><br>
                        <span class="small">{{ $val }}</span>
                    </div>
                @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- ── RAW JSON VIEW ─────────────────────────────────────── --}}
        <div class="d-none card-body p-3" id="ctx-raw-view">
            <pre class="bg-light rounded p-3 small mb-0" style="max-height:400px;overflow:auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        @else
        {{-- Tidak ada schema --}}
        <div class="card-body p-3">
            <div class="alert alert-info small mb-2">
                <i class="bi bi-info-circle"></i>
                Belum ada Form Schema untuk jenis dokumen ini.
                <a href="{{ route('master.document-type.index') }}" target="_blank">Atur di Master → Document Type</a>.
            </div>
            <pre class="bg-light rounded p-3 small mb-0" style="max-height:400px;overflow:auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function switchView(mode) {
    const fv = document.getElementById('ctx-form-view');
    const rv = document.getElementById('ctx-raw-view');
    const bf = document.getElementById('btn-vf');
    const br = document.getElementById('btn-vr');
    if (!fv) return;
    if (mode === 'form') {
        fv.classList.remove('d-none'); rv.classList.add('d-none');
        bf.classList.add('active');    br.classList.remove('active');
    } else {
        rv.classList.remove('d-none'); fv.classList.add('d-none');
        br.classList.add('active');    bf.classList.remove('active');
    }
}
</script>
@endpush
