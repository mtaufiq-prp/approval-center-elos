<?php $__env->startSection('title','Flow Version Detail'); ?>
<?php $__env->startSection('workflow_content'); ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?php echo e(route('workflow.flow-definition.index')); ?>">Flow Definition</a></li>
    <li class="breadcrumb-item"><a href="<?php echo e(route('workflow.flow-version.index', $version->idtblflow_definition)); ?>"><?php echo e(optional($version->flowDefinition)->flow_code); ?></a></li>
    <li class="breadcrumb-item active">v<?php echo e($version->version_no); ?></li>
</ol></nav>

<?php if(session('error')): ?>
    <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
<?php endif; ?>
<?php if(session('status')): ?>
    <div class="alert alert-success"><?php echo e(session('status')); ?></div>
<?php endif; ?>

<?php if(session('validation_result')): ?>
    <?php $vr = session('validation_result'); ?>
    <?php if(!empty($vr['errors'])): ?>
        <div class="alert alert-danger small"><strong>Errors:</strong><ul class="mb-0"><?php $__currentLoopData = $vr['errors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
    <?php endif; ?>
    <?php if(!empty($vr['warnings'])): ?>
        <div class="alert alert-warning small"><strong>Warnings:</strong><ul class="mb-0"><?php $__currentLoopData = $vr['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($w); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
    <?php endif; ?>
    <?php if(!empty($vr['checks'])): ?>
        <div class="alert alert-light small"><strong>Checks:</strong><ul class="mb-0"><?php $__currentLoopData = $vr['checks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($c); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
    <?php endif; ?>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 small">
            <div class="col-md-3"><strong>Flow:</strong> <?php echo e(optional($version->flowDefinition)->flow_code); ?></div>
            <div class="col-md-3"><strong>Version:</strong> v<?php echo e($version->version_no); ?> — <?php echo e($version->version_name); ?></div>
            <div class="col-md-2"><strong>Status:</strong>
                <?php $cls=['DRAFT'=>'secondary','ACTIVE'=>'success','INACTIVE'=>'warning','ARCHIVED'=>'dark'][$version->status]??'light'; ?>
                <span class="badge bg-<?php echo e($cls); ?>"><?php echo e($version->status); ?></span>
            </div>
            <div class="col-md-4"><strong>Validation:</strong>
                <?php $vc=['DRAFT'=>'secondary','VALID'=>'success','INVALID'=>'danger'][$version->validation_status??'DRAFT']??'secondary'; ?>
                <span class="badge bg-<?php echo e($vc); ?>"><?php echo e($version->validation_status ?? 'DRAFT'); ?></span>
                <?php if($version->validated_at): ?> <small class="text-muted"><?php echo e($version->validated_at->format('Y-m-d H:i')); ?></small> <?php endif; ?>
            </div>
            <?php if($version->validation_message): ?>
                <div class="col-12 text-muted"><?php echo e($version->validation_message); ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <a href="<?php echo e(route('workflow.flow-version.builder', $version->idtblflow_version)); ?>"
               class="btn btn-sm btn-primary">
                <i class="bi bi-diagram-3"></i> Visual Builder
            </a>
            <a href="<?php echo e(route('workflow.flow-node.index', $version->idtblflow_version)); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-circle"></i> Nodes (<?php echo e($version->steps->count()); ?>)
            </a>
            <a href="<?php echo e(route('workflow.flow-edge.index', $version->idtblflow_version)); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-right"></i> Edges (<?php echo e($version->transitions->count()); ?>)
            </a>
            <a href="<?php echo e(route('workflow.flow-version.preview', $version->idtblflow_version)); ?>" class="btn btn-sm btn-outline-info">
                <i class="bi bi-eye"></i> Preview
            </a>
            <form method="POST" action="<?php echo e(route('workflow.flow-version.validate', $version->idtblflow_version)); ?>" class="d-inline">
                <?php echo csrf_field(); ?> <button class="btn btn-sm btn-outline-warning"><i class="bi bi-check-circle"></i> Validate</button>
            </form>
            <?php if($version->isDraft() && $version->isValidated()): ?>
            <form method="POST" action="<?php echo e(route('workflow.flow-version.deploy', $version->idtblflow_version)); ?>" class="d-inline" data-confirm="Deploy version ini menjadi ACTIVE?">
                <?php echo csrf_field(); ?> <button class="btn btn-sm btn-success"><i class="bi bi-rocket"></i> Deploy</button>
            </form>
            <?php endif; ?>
            <form method="POST" action="<?php echo e(route('workflow.flow-version.clone', $version->idtblflow_version)); ?>" class="d-inline" data-confirm="Clone version ini ke version baru?">
                <?php echo csrf_field(); ?> <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-copy"></i> Clone</button>
            </form>
            <a href="<?php echo e(route('workflow.flow-version.edit', $version->idtblflow_version)); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit Metadata
            </a>
        </div>
        <?php if($version->isActive() && $version->isInUse()): ?>
            <div class="alert alert-warning small mt-3 mb-0">
                <i class="bi bi-lock"></i> Version ini sudah ACTIVE dan dipakai approval request.
                Node, edge, dan assignee rule <strong>tidak dapat diedit</strong>. Gunakan <strong>Clone</strong> untuk membuat versi baru.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light small fw-semibold">Nodes</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light small"><tr>
                    <th>node_code</th><th>step_name</th><th>Type</th><th>Gateway</th><th>Assignee Rules</th><th>step_order</th>
                </tr></thead>
                <tbody class="small">
                <?php $__currentLoopData = $version->steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><code><?php echo e($n->node_code); ?></code></td>
                        <td><?php echo e($n->step_name); ?></td>
                        <td><span class="badge bg-<?php echo e(['START'=>'success','END'=>'danger','DECISION'=>'warning','APPROVAL'=>'primary'][$n->step_type]??'secondary'); ?>"><?php echo e($n->step_type); ?></span></td>
                        <td><small><?php echo e($n->gateway_type !== 'NONE' ? $n->gateway_type : '-'); ?></small></td>
                        <td><?php echo e($n->activeAssigneeRules->count()); ?></td>
                        <td><?php echo e($n->step_order); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/workflow/flow_version/show.blade.php ENDPATH**/ ?>