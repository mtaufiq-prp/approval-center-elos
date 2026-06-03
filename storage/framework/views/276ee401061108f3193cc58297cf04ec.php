<?php $__env->startSection('title', 'Inbox'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-inbox"></i> Inbox
        <?php if($inboxCount > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo e($inboxCount); ?></span>
        <?php endif; ?>
    </h5>
</div>

 
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" href="<?php echo e(route('inbox.index')); ?>">
            <i class="bi bi-inbox"></i> Inbox
            <?php if($inboxCount > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo e($inboxCount); ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo e(route('inbox.history')); ?>">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                       class="form-control form-control-sm" placeholder="Cari no request / judul...">
            </div>
            <div class="col-md-3">
                <select name="overdue" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" <?php echo e(request('overdue')==='1' ? 'selected':''); ?>>Overdue saja</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-outline-primary w-100">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>No Request</th><th>Judul</th><th>Source App</th>
                        <th>Step</th><th>Prioritas</th><th>Due</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $overdue = $task->due_at && $task->due_at->isPast(); ?>
                    <tr class="<?php echo e($overdue ? 'table-warning' : ''); ?>">
                        <td><code><?php echo e(optional($task->approvalRequest)->source_request_no ?? '-'); ?></code></td>
                        <td><?php echo e(Str::limit(optional($task->approvalRequest)->title, 45)); ?></td>
                        <td><?php echo e(optional(optional($task->approvalRequest)->sourceApp)->app_code); ?></td>
                        <td><?php echo e(optional($task->flowStep)->step_name); ?></td>
                        <td>
                            <?php $p = optional($task->approvalRequest)->priority; ?>
                            <span class="badge bg-<?php echo e(match($p) { 'URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary', default=>'info' }); ?>">
                                <?php echo e($p ?? '-'); ?>

                            </span>
                        </td>
                        <td>
                            <?php if($task->due_at): ?>
                                <span class="<?php echo e($overdue ? 'text-danger fw-bold' : 'text-muted'); ?>">
                                    <?php echo e($task->due_at->format('d/m H:i')); ?>

                                    <?php if($overdue): ?> <i class="bi bi-alarm-fill"></i> <?php endif; ?>
                                </span>
                            <?php else: ?> <span class="text-muted">—</span> <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo e(route('inbox.show', $task->idtbltask)); ?>"
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right-circle"></i> Buka
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                        Inbox kosong. Tidak ada task yang menunggu.
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/inbox/index.blade.php ENDPATH**/ ?>