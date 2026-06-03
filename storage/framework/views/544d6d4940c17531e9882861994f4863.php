<?php $__env->startSection('title','Flow Versions'); ?>
<?php $__env->startSection('workflow_content'); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?php echo e(route('workflow.flow-definition.index')); ?>">Flow Definition</a></li>
    <li class="breadcrumb-item active"><?php echo e($definition->flow_code); ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-layers"></i> Versions — <?php echo e($definition->flow_name); ?></h5>
    <a href="<?php echo e(route('workflow.flow-version.create', $definition->idtblflow_definition)); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Version</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>v#</th><th>Name</th><th>Status</th><th>Validation</th><th>Nodes</th><th>Edges</th><th>In Use</th><th>Deployed</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><strong>v<?php echo e($v->version_no); ?></strong></td>
                    <td><?php echo e($v->version_name); ?></td>
                    <td>
                        <?php $cls=['DRAFT'=>'secondary','ACTIVE'=>'success','INACTIVE'=>'warning','ARCHIVED'=>'dark'][$v->status]??'light'; ?>
                        <span class="badge bg-<?php echo e($cls); ?>"><?php echo e($v->status); ?></span>
                    </td>
                    <td>
                        <?php $vc=['DRAFT'=>'secondary','VALID'=>'success','INVALID'=>'danger'][$v->validation_status??'DRAFT']??'secondary'; ?>
                        <span class="badge bg-<?php echo e($vc); ?>"><?php echo e($v->validation_status ?? 'DRAFT'); ?></span>
                    </td>
                    <td><?php echo e($v->steps_count); ?></td>
                    <td><?php echo e($v->transitions_count); ?></td>
                    <td><?php echo e($v->in_use_count > 0 ? '⚠️ '.$v->in_use_count.' req' : '-'); ?></td>
                    <td class="text-muted small"><?php echo e(optional($v->deployed_at)->format('Y-m-d H:i') ?? '-'); ?></td>
                    <td class="text-end">
                        <a href="<?php echo e(route('workflow.flow-version.show', $v->idtblflow_version)); ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                        <a href="<?php echo e(route('workflow.flow-version.preview', $v->idtblflow_version)); ?>" class="btn btn-sm btn-outline-info">Preview</a>
                        <?php if($v->status === 'DRAFT'): ?>
                        <form method="POST" action="<?php echo e(route('workflow.flow-version.deploy', $v->idtblflow_version)); ?>" class="d-inline" data-confirm="Deploy version ini menjadi ACTIVE?">
                            <?php echo csrf_field(); ?> <button class="btn btn-sm btn-success">Deploy</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo e(route('workflow.flow-version.clone', $v->idtblflow_version)); ?>" class="d-inline" data-confirm="Clone version ini?">
                            <?php echo csrf_field(); ?> <button class="btn btn-sm btn-outline-warning">Clone</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="9" class="text-center text-muted py-4">Belum ada version.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/workflow/flow_version/index.blade.php ENDPATH**/ ?>