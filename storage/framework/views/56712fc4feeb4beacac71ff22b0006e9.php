<?php $__env->startSection('title', 'Approval Group'); ?>
<?php $__env->startSection('master_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Approval Group</h5>
    <a href="<?php echo e(route('master.approval-group.create')); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control form-control-sm" placeholder="Cari..."></div>
        <div class="col-md-3"><select name="is_active" class="form-select form-select-sm">
            <option value="all">Semua status</option>
            <option value="1" <?php echo e(request('is_active') === '1' ? 'selected' : ''); ?>>Aktif</option>
            <option value="0" <?php echo e(request('is_active') === '0' ? 'selected' : ''); ?>>Nonaktif</option>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Code</th><th>Name</th><th>Members</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><code><?php echo e($item->group_code); ?></code></td>
                    <td><?php echo e($item->group_name); ?></td>
                    <td><span class="badge bg-info"><?php echo e($item->members_count); ?></span></td>
                    <td><?php if($item->is_active): ?><span class="badge bg-success">Aktif</span><?php else: ?><span class="badge bg-secondary">Nonaktif</span><?php endif; ?></td>
                    <td class="text-end">
                        <a href="<?php echo e(route('master.approval-group.edit', $item->idtblapproval_group)); ?>" class="btn btn-sm btn-outline-primary">Edit & Member</a>
                        <form method="POST" action="<?php echo e(route('master.approval-group.destroy', $item->idtblapproval_group)); ?>"
                              class="d-inline" data-confirm="Yakin?">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="btn btn-sm btn-outline-<?php echo e($item->is_active ? 'danger' : 'success'); ?>">
                                <?php echo e($item->is_active ? 'Deactivate' : 'Activate'); ?>

                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr> <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/approval_group/index.blade.php ENDPATH**/ ?>