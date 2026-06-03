<?php $__env->startSection('title', 'Edit Source App'); ?>

<?php $__env->startSection('master_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-app-indicator"></i> Edit Source App: <?php echo e($item->app_code); ?></h5>
</div>

<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="<?php echo e(route('master.source-app.update', $item->idtblsource_app)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <?php echo $__env->make('master.source_app._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </form>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/source_app/edit.blade.php ENDPATH**/ ?>