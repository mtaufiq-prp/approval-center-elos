<?php $__env->startSection('title','Tambah Flow Definition'); ?>
<?php $__env->startSection('workflow_content'); ?>
<h5 class="mb-3">Tambah Flow Definition</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="<?php echo e(route('workflow.flow-definition.store')); ?>"><?php echo csrf_field(); ?>
<?php echo $__env->make('workflow.flow_definition._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</form></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/workflow/flow_definition/create.blade.php ENDPATH**/ ?>