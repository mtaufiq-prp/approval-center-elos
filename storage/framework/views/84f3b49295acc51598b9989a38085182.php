<?php $__env->startSection('title', 'Flow Definition'); ?>
<?php $__env->startSection('workflow_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-diagram-2"></i> Flow Definition</h5>
    <a href="<?php echo e(route('workflow.flow-definition.create')); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control form-control-sm" placeholder="Cari..."></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sa->idtblsource_app); ?>" <?php echo e((string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':''); ?>><?php echo e($sa->app_code); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Flow Code</th><th>Flow Name</th><th>App</th><th>Doc Type</th><th>Versions</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><code><?php echo e($item->flow_code); ?></code></td>
                    <td><?php echo e($item->flow_name); ?></td>
                    <td><?php echo e(optional($item->sourceApp)->app_code); ?></td>
                    <td><?php echo e(optional($item->documentType)->doc_code); ?></td>
                    <td><span class="badge bg-secondary"><?php echo e($item->versions_count); ?></span></td>
                    <td><?php if($item->is_active): ?><span class="badge bg-success">Aktif</span><?php else: ?><span class="badge bg-secondary">Nonaktif</span><?php endif; ?></td>
                    <td class="text-end">
                        <a href="<?php echo e(route('workflow.flow-version.index', $item->idtblflow_definition)); ?>" class="btn btn-sm btn-outline-info">Versions</a>
                        <a href="<?php echo e(route('workflow.flow-definition.edit', $item->idtblflow_definition)); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="<?php echo e(route('workflow.flow-definition.destroy', $item->idtblflow_definition)); ?>" class="d-inline" data-confirm="Yakin?">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="btn btn-sm btn-outline-<?php echo e($item->is_active?'danger':'success'); ?>"><?php echo e($item->is_active?'Deactivate':'Activate'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/workflow/flow_definition/index.blade.php ENDPATH**/ ?>