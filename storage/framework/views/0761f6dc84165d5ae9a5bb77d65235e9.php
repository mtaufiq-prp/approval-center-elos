<?php $__env->startSection('title', 'Detail Request'); ?>

<?php $__env->startSection('content'); ?>
<?php
$req = $approval_request;
$sc = ['SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
       'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'];
?>

<div class="mb-3">
    <a href="<?php echo e(route('monitoring.index')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="row g-3">
    
    <div class="col-md-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header small d-flex justify-content-between">
                <span><i class="bi bi-file-earmark-text"></i> Detail Request</span>
                <span class="badge bg-<?php echo e($sc[$req->request_status] ?? 'secondary'); ?> fs-6"><?php echo e($req->request_status); ?></span>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">No Request</dt><dd class="col-7"><code><?php echo e($req->source_request_no); ?></code></dd>
                    <dt class="col-5">Judul</dt><dd class="col-7"><?php echo e($req->title); ?></dd>
                    <dt class="col-5">Source App</dt><dd class="col-7"><?php echo e(optional($req->sourceApp)->app_code); ?></dd>
                    <dt class="col-5">Tipe Dok</dt><dd class="col-7"><?php echo e(optional($req->documentType)->doc_name); ?></dd>
                    <dt class="col-5">Pemohon</dt><dd class="col-7"><?php echo e($req->requester_name); ?><br><code class="small"><?php echo e($req->requester_ref); ?></code></dd>
                    <dt class="col-5">Org</dt><dd class="col-7"><?php echo e($req->requester_org_name ?? '—'); ?></dd>
                    <?php if($req->amount): ?><dt class="col-5">Nilai</dt><dd class="col-7"><?php echo e($req->currency_code); ?> <?php echo e(number_format($req->amount, 2)); ?></dd><?php endif; ?>
                    <dt class="col-5">Prioritas</dt>
                    <dd class="col-7">
                        <?php $prioBadge = ['URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary'][$req->priority] ?? 'info'; ?>
                        <span class="badge bg-<?php echo e($prioBadge); ?>"><?php echo e($req->priority); ?></span>
                    </dd>
                    <dt class="col-5">Flow</dt><dd class="col-7"><?php echo e(optional(optional(optional($req->processInstance)->flowVersion)->flowDefinition)->flow_name ?? '—'); ?></dd>
                    <dt class="col-5">Version</dt><dd class="col-7">v<?php echo e(optional(optional($req->processInstance)->flowVersion)->version_no ?? '—'); ?></dd>
                    <dt class="col-5">Step Kini</dt><dd class="col-7"><?php echo e(optional(optional($req->processInstance)->flowStepCurrent)->step_name ?? '—'); ?></dd>
                    <dt class="col-5">Dibuat</dt><dd class="col-7"><?php echo e($req->created_at?->format('d M Y H:i')); ?></dd>
                    <dt class="col-5">Diperbarui</dt><dd class="col-7"><?php echo e($req->updated_at?->format('d M Y H:i')); ?></dd>
                </dl>
            </div>
        </div>

        
        <div class="card shadow-sm mb-3">
            <div class="card-header small"><i class="bi bi-list-task"></i> Daftar Task</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Step</th><th>Assignee</th><th>Status</th><th>Selesai</th></tr></thead>
                    <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $taskBadge = ['APPROVED'=>'success','REJECTED'=>'danger','OPEN'=>'warning',
                                          'CANCELLED'=>'secondary','RETURNED'=>'warning','CLAIMED'=>'info',
                                          'EXPIRED'=>'dark','SKIPPED'=>'secondary'][$t->task_status] ?? 'info';
                        ?>
                        <tr>
                            <td><?php echo e(optional($t->flowStep)->step_name); ?></td>
                            <td><code class="small"><?php echo e(optional($t->completedBy)->user_ref ?? optional($t->claimedBy)->user_ref ?? optional($t->assignedTo)->user_ref ?? '—'); ?></code></td>
                            <td>
                                <span class="badge bg-<?php echo e($taskBadge); ?>">
                                    <?php echo e($t->task_status); ?>

                                </span>
                            </td>
                            <td class="text-muted"><?php echo e($t->completed_at?->format('d/m H:i') ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="4" class="text-muted text-center">Belum ada task.</td></tr> <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    
    <div class="col-md-7">
        
        <?php $ctxArr = is_array($req->context_json) ? $req->context_json : json_decode($req->context_json ?? '{}', true); ?>
        <?php $payArr = is_array($req->payload_json) ? $req->payload_json : json_decode($req->payload_json ?? '{}', true); ?>
        <?php echo $__env->make('partials._context_renderer', [
            'payloadJson' => $payArr,
            'contextJson' => $ctxArr,
            'formSchema'  => optional($req->documentType)->form_schema ?? [],
            'docTypeName' => optional($req->documentType)->doc_name,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        
        <div class="card shadow-sm mb-3">
            <div class="card-header small"><i class="bi bi-clock-history"></i> Riwayat Keputusan</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Catatan</th></tr></thead>
                    <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $actionLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $al): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $alBadge = in_array($al->action_code, ['APPROVE','AUTO_APPROVE']) ? 'success'
                                : ($al->action_code === 'REJECT' ? 'danger'
                                : ($al->action_code === 'RETURN' ? 'warning'
                                : ($al->action_code === 'CANCEL' ? 'secondary' : 'info')));
                        ?>
                        <tr>
                            <td class="text-nowrap text-muted"><?php echo e($al->created_at?->format('d/m H:i')); ?></td>
                            <td><code><?php echo e($al->actor_ref); ?></code></td>
                            <td><span class="badge bg-<?php echo e($alBadge); ?>"><?php echo e($al->action_code); ?></span></td>
                            <td class="text-muted"><?php echo e(Str::limit($al->action_note, 60)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="4" class="text-muted text-center py-3">Belum ada keputusan.</td></tr> <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card shadow-sm">
            <div class="card-header small">
                <i class="bi bi-map"></i> Route Log (Jejak Engine)
                <button class="btn btn-xs btn-outline-secondary btn-sm float-end"
                        data-bs-toggle="collapse" data-bs-target="#routeLog">Toggle</button>
            </div>
            <div class="collapse show" id="routeLog">
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light sticky-top"><tr><th>Waktu</th><th>Event</th><th>Node</th><th>Pesan</th></tr></thead>
                        <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $req->routeLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="text-nowrap text-muted"><?php echo e($log->created_at?->format('d/m H:i:s')); ?></td>
                                <td><code class="small"><?php echo e($log->route_event); ?></code></td>
                                <td><?php echo e(optional($log->flowStep)->node_code ?? '—'); ?></td>
                                <td class="text-muted"><?php echo e($log->message); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="4" class="text-muted text-center py-3">Belum ada route log.</td></tr> <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/monitoring/show.blade.php ENDPATH**/ ?>