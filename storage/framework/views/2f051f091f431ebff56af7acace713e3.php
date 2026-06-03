<?php $__env->startSection('title', 'Delegation'); ?>
<?php $__env->startSection('master_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-person-check"></i> Delegation</h5>
    <a href="<?php echo e(route('master.delegation.create')); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-6"><input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control form-control-sm" placeholder="Cari user_ref / nama..."></div>
        <div class="col-md-3"><select name="status" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Aktif (saat ini)</option>
            <option value="future" <?php echo e(request('status') === 'future' ? 'selected' : ''); ?>>Akan datang</option>
            <option value="expired" <?php echo e(request('status') === 'expired' ? 'selected' : ''); ?>>Lewat</option>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Delegator</th><th>Delegate</th><th>Source App</th><th>Doc</th><th>Periode</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php $now = now(); $inPeriod = $now->between($item->start_at, $item->end_at); ?>
                <tr>
                    <td><code><?php echo e(optional($item->delegator)->user_ref); ?></code> <?php echo e(optional($item->delegator)->full_name); ?></td>
                    <td><code><?php echo e(optional($item->delegate)->user_ref); ?></code> <?php echo e(optional($item->delegate)->full_name); ?></td>
                    <td><?php echo e(optional($item->sourceApp)->app_code ?: 'ALL'); ?></td>
                    <td><?php echo e(optional($item->documentType)->doc_code ?: 'ALL'); ?></td>
                    <td><?php echo e($item->start_at->format('Y-m-d H:i')); ?> → <?php echo e($item->end_at->format('Y-m-d H:i')); ?></td>
                    <td>
                        <?php if(!$item->is_active): ?> <span class="badge bg-secondary">Stopped</span>
                        <?php elseif($inPeriod): ?> <span class="badge bg-success">Active</span>
                        <?php elseif($now->lt($item->start_at)): ?> <span class="badge bg-warning text-dark">Future</span>
                        <?php else: ?> <span class="badge bg-light text-dark">Expired</span> <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?php echo e(route('master.delegation.edit', $item->idtbldelegation)); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="<?php echo e(route('master.delegation.destroy', $item->idtbldelegation)); ?>"
                              class="d-inline" data-confirm="Yakin?">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="btn btn-sm btn-outline-<?php echo e($item->is_active ? 'danger' : 'success'); ?>">
                                <?php echo e($item->is_active ? 'Stop' : 'Resume'); ?>

                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr> <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/delegation/index.blade.php ENDPATH**/ ?>