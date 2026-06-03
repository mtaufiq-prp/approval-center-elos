<?php $__env->startSection('title', 'Monitoring Approval'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-list-check"></i> Monitoring Approval Request</h5>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                       class="form-control form-control-sm" placeholder="Cari no/judul/pemohon...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua status</option>
                    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($s); ?>" <?php echo e(request('status')===$s ? 'selected':''); ?>><?php echo e($s); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="idtblsource_app" class="form-select form-select-sm">
                    <option value="">Semua app</option>
                    <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($sa->idtblsource_app); ?>" <?php echo e((string)request('idtblsource_app')===(string)$sa->idtblsource_app ? 'selected':''); ?>>
                            <?php echo e($sa->app_code); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">Semua prioritas</option>
                    <?php $__currentLoopData = ['LOW','NORMAL','HIGH','URGENT']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($p); ?>" <?php echo e(request('priority')===$p ? 'selected':''); ?>><?php echo e($p); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-1">
                <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>"
                       class="form-control form-control-sm" title="Dari tanggal">
            </div>
            <div class="col-md-1">
                <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>"
                       class="form-control form-control-sm" title="Sampai tanggal">
            </div>
            <div class="col-12 text-end">
                <a href="<?php echo e(route('monitoring.index')); ?>" class="btn btn-sm btn-outline-secondary me-1">Reset</a>
                <button class="btn btn-sm btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>No Request</th><th>Judul</th><th>App</th><th>Pemohon</th>
                        <th>Prioritas</th><th>Status</th><th>Step Saat Ini</th><th>Dibuat</th><th></th>
                    </tr>
                </thead>
                <tbody class="small">
                <?php
                $sc = ['SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
                       'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'];
                ?>
                <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><code><?php echo e($req->source_request_no ?? '-'); ?></code></td>
                        <td><?php echo e(Str::limit($req->title, 35)); ?></td>
                        <td><?php echo e(optional($req->sourceApp)->app_code); ?></td>
                        <td><?php echo e($req->requester_name); ?></td>
                        <td><span class="badge bg-<?php echo e(match($req->priority){'URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary',default=>'info'}); ?>"><?php echo e($req->priority); ?></span></td>
                        <td><span class="badge bg-<?php echo e($sc[$req->request_status] ?? 'secondary'); ?>"><?php echo e($req->request_status); ?></span></td>
                        <td class="text-muted"><?php echo e(optional(optional($req->processInstance)->flowStepCurrent)->step_name ?? '—'); ?></td>
                        <td class="text-muted text-nowrap"><?php echo e($req->created_at?->format('d/m/y H:i')); ?></td>
                        <td>
                            <a href="<?php echo e(route('monitoring.show', $req->idtblapproval_request)); ?>"
                               class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">Tidak ada data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center small text-muted">
        <span>Total: <?php echo e($items->total()); ?></span>
        <?php echo e($items->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/monitoring/index.blade.php ENDPATH**/ ?>