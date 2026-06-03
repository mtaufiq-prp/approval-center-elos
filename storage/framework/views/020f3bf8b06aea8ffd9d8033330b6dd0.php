
<?php
    // Gunakan payload_json jika ada, fallback ke context_json
    $payload    = $payloadJson ?? $contextJson ?? [];
    $ctx        = $contextJson ?? [];
    $schema     = $formSchema  ?? [];
    $hasSchema  = !empty($schema);

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
?>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
            <i class="bi bi-file-earmark-text"></i> Data Dokumen
            <?php if(!empty($docTypeName)): ?>
                <span class="badge bg-primary ms-1"><?php echo e($docTypeName); ?></span>
            <?php endif; ?>
        </span>
        <?php if($hasSchema): ?>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary active" id="btn-vf" onclick="switchView('form')">
                <i class="bi bi-layout-text-window"></i> Form
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btn-vr" onclick="switchView('raw')">
                <i class="bi bi-code-slash"></i> Raw JSON
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="collapse show" id="ctx-body">

        
        <?php if($hasSchema): ?>
        <div class="card-body p-3" id="ctx-form-view">
            <div class="row g-3">
            <?php $__currentLoopData = $schema; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
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
                ?>

                <?php if($type === 'separator'): ?>
                    <div class="col-12 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-primary small text-uppercase" style="letter-spacing:.06em;white-space:nowrap">
                                <?php echo e($label ?: 'Detail'); ?>

                            </span>
                            <hr class="flex-fill m-0">
                        </div>
                    </div>

                <?php elseif($type === 'table'): ?>
                    <?php
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
                    ?>
                    <div class="<?php echo e($colCls); ?>">
                        <div class="small text-muted mb-1"><?php echo e($label); ?></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:32px">#</th>
                                        <?php $__currentLoopData = $colLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e($cl); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $tableData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ri => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td class="text-center text-muted"><?php echo e($ri + 1); ?></td>
                                            <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php $cv = is_array($row) ? ($row[$col] ?? '—') : '—'; ?>
                                                <td>
                                                    <?php if(is_numeric($cv) && in_array(strtolower($col), ['value_retur','value_retur_ori','value_potong_budget','harga','nilai'])): ?>
                                                        <?php echo e(number_format((float)$cv, 0, ',', '.')); ?>

                                                    <?php else: ?>
                                                        <?php echo e($cv); ?>

                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="<?php echo e(count($columns)+1); ?>" class="text-center text-muted">Tidak ada data</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if(count($tableData) > 0): ?>
                                <?php
                                    $numCols = array_filter($columns, fn($c) =>
                                        isset($tableData[0][$c]) && is_numeric($tableData[0][$c])
                                    );
                                ?>
                                <?php if(count($numCols) > 0): ?>
                                <tfoot class="table-light fw-semibold">
                                    <tr>
                                        <td colspan="<?php echo e(count($columns) - count($numCols) + 1); ?>" class="text-end small text-muted">Total</td>
                                        <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php if(in_array($col, $numCols)): ?>
                                                <td><?php echo e(number_format(array_sum(array_column($tableData, $col)), 0, ',', '.')); ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="<?php echo e($colCls); ?>">
                        <div class="small text-muted mb-1"><?php echo e($label); ?></div>
                        <div>
                        <?php if($rawVal === null || $rawVal === ''): ?>
                            <span class="text-muted small"><?php echo e($default); ?></span>
                        <?php elseif($type === 'currency'): ?>
                            <?php $prefix = $fd['prefix'] ?? 'Rp '; ?>
                            <span class="fw-semibold text-success"><?php echo e($prefix . number_format((float)$rawVal, 0, ',', '.')); ?></span>
                        <?php elseif($type === 'number'): ?>
                            <span class="fw-semibold"><?php echo e(number_format((float)$rawVal, 0, ',', '.')); ?></span>
                        <?php elseif($type === 'badge'): ?>
                            <?php $bc = ($fd['colors'] ?? [])[$rawVal] ?? 'secondary'; ?>
                            <span class="badge bg-<?php echo e($bc); ?> px-3 py-1 fs-6"><?php echo e($rawVal); ?></span>
                        <?php elseif($type === 'date'): ?>
                            <?php try { echo \Carbon\Carbon::parse($rawVal)->translatedFormat('d F Y'); } catch(\Exception $e) { echo $rawVal; } ?>
                        <?php elseif($type === 'datetime'): ?>
                            <?php try { echo \Carbon\Carbon::parse($rawVal)->translatedFormat('d F Y, H:i'); } catch(\Exception $e) { echo $rawVal; } ?>
                        <?php elseif($type === 'textarea'): ?>
                            <div class="bg-light border rounded p-2 small" style="white-space:pre-wrap;min-height:40px"><?php echo e($rawVal); ?></div>
                        <?php elseif($type === 'image'): ?>
                            <a href="<?php echo e($rawVal); ?>" target="_blank">
                                <span class="badge bg-info"><i class="bi bi-image"></i> <?php echo e(Str::limit($rawVal, 40)); ?></span>
                            </a>
                        <?php elseif($type === 'list' && is_array($rawVal)): ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php $__currentLoopData = $rawVal; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="badge bg-light text-dark border"><?php echo e($item); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php else: ?>
                            <?php echo e(is_array($rawVal) ? json_encode($rawVal, JSON_UNESCAPED_UNICODE) : $rawVal); ?>

                        <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            
            <?php
                // Cek flat ctx untuk field yang tidak ada di schema
                $extras = array_diff_key($ctx, array_flip($schemaFields));
                // Hapus field yang sudah ada lewat prefix match
                foreach ($schemaFields as $sf) {
                    $last = last(explode('.', $sf));
                    unset($extras[$last]);
                }
                $extras = array_filter($extras, fn($v) => !is_array($v) && $v !== null && $v !== '');
            ?>
            <?php if(!empty($extras)): ?>
            <div class="mt-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="small text-muted text-uppercase" style="font-size:10px;letter-spacing:.05em">Data Tambahan</span>
                    <hr class="flex-fill m-0">
                </div>
                <div class="row g-2">
                <?php $__currentLoopData = $extras; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="col-md-6">
                        <span class="small text-muted"><?php echo e($key); ?></span><br>
                        <span class="small"><?php echo e($val); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        
        <div class="d-none card-body p-3" id="ctx-raw-view">
            <pre class="bg-light rounded p-3 small mb-0" style="max-height:400px;overflow:auto"><?php echo e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </div>

        <?php else: ?>
        
        <div class="card-body p-3">
            <div class="alert alert-info small mb-2">
                <i class="bi bi-info-circle"></i>
                Belum ada Form Schema untuk jenis dokumen ini.
                <a href="<?php echo e(route('master.document-type.index')); ?>" target="_blank">Atur di Master → Document Type</a>.
            </div>
            <pre class="bg-light rounded p-3 small mb-0" style="max-height:400px;overflow:auto"><?php echo e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php /**PATH /var/www/html/approval_center/resources/views/partials/_context_renderer.blade.php ENDPATH**/ ?>