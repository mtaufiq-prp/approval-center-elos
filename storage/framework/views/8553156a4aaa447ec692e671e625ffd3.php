<?php $__env->startSection('title', 'Riwayat Keputusan'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $decisionBadge = [
        'APPROVED'  => ['success', 'check-circle',          'Disetujui'],
        'REJECTED'  => ['danger',  'x-circle',              'Ditolak'],
        'RETURNED'  => ['warning', 'arrow-counterclockwise','Dikembalikan'],
        'CANCELLED' => ['secondary','slash-circle',         'Dibatalkan'],
        'SKIPPED'   => ['light',   'skip-forward',          'Dilewati'],
        'EXPIRED'   => ['dark',    'hourglass-bottom',      'Expired'],
    ];
    $activeDecision = request('decision');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Keputusan</h5>
</div>


<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?php echo e(route('inbox.index')); ?>">
            <i class="bi bi-inbox"></i> Inbox
            <?php if($inboxCount > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo e($inboxCount); ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?php echo e(route('inbox.history')); ?>">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                       class="form-control form-control-sm" placeholder="Cari no request / judul...">
            </div>
            <div class="col-md-4">
                <select name="decision" class="form-select form-select-sm">
                    <option value="">Semua keputusan</option>
                    <?php $__currentLoopData = $decisionBadge; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => [$c,$i,$lbl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($code); ?>" <?php echo e($activeDecision === $code ? 'selected':''); ?>><?php echo e($lbl); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
                <?php if(request('search') || request('decision')): ?>
                    <a href="<?php echo e(route('inbox.history')); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php
            $reqStatusColor = [ 
                'DRAFT'=>'secondary','SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary',
                'APPROVED'=>'success','REJECTED'=>'danger','RETURNED'=>'warning',
                'CANCELLED'=>'secondary','ERROR'=>'danger',
            ];
        ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>Waktu Aksi</th>
                        <th>No Request</th>
                        <th>Judul</th>
                        <th>Source</th>
                        <th>Step yang Saya Aksi</th>
                        <th>Keputusan Saya</th>
                        <th>Catatan</th>
                        <th>Status Request Sekarang</th>
                        <th class="text-end">Aksi</th> 
                    </tr>
                </thead>
                <tbody class="small">
                <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        [$color, $icon, $label] = $decisionBadge[$task->task_status] ?? ['info','info-circle',$task->task_status];
                        $reqStatus = optional($task->approvalRequest)->request_status;
                        $reqColor  = $reqStatusColor[$reqStatus] ?? 'secondary';
                        $curStep   = optional(optional($task->approvalRequest)->flowStepCurrent)->step_name;
                    ?>
                    <tr>
                        <td class="text-nowrap text-muted">
                            <?php echo e($task->completed_at?->format('d/m/Y H:i') ?? '—'); ?>

                        </td>
                        <td><code><?php echo e(optional($task->approvalRequest)->source_request_no ?? '-'); ?></code></td>
                        <td><?php echo e(\Illuminate\Support\Str::limit(optional($task->approvalRequest)->title, 40)); ?></td>
                        <td><?php echo e(optional(optional($task->approvalRequest)->sourceApp)->app_code); ?></td>
                        <td><?php echo e(optional($task->flowStep)->step_name); ?></td>
                        <td>
                            <span class="badge bg-<?php echo e($color); ?>">
                                <i class="bi bi-<?php echo e($icon); ?>"></i> <?php echo e($label); ?>

                            </span>
                        </td>
                        <td>
                            <?php if($task->decision_note): ?>
                                <span class="text-muted" title="<?php echo e($task->decision_note); ?>">
                                    <?php echo e(\Illuminate\Support\Str::limit($task->decision_note, 60)); ?>

                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo e($reqColor); ?>"><?php echo e($reqStatus ?? '—'); ?></span>
                            <?php if($curStep && !in_array($reqStatus, ['APPROVED','REJECTED','CANCELLED'])): ?>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-geo-alt"></i> <?php echo e($curStep); ?>

                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo e(route('inbox.show', $task->idtbltask)); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Lihat
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Belum ada riwayat keputusan.
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/inbox/history.blade.php ENDPATH**/ ?>