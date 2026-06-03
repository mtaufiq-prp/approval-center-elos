<?php $__env->startSection('title', 'Edit Document Type'); ?>
<?php $__env->startSection('master_content'); ?>
<h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> Edit Document Type: <?php echo e($item->doc_code); ?></h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="<?php echo e(route('master.document-type.update', $item->idtbldocument_type)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?> <?php echo $__env->make('master.document_type._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></form>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/document_type/edit.blade.php ENDPATH**/ ?>