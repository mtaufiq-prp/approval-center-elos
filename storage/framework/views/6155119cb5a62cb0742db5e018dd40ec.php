<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('form[data-confirm]').forEach(f=>{
        f.addEventListener('submit',e=>{ if(!confirm(f.dataset.confirm)) e.preventDefault(); });
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="row">
    <aside class="col-lg-3 col-xl-2 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold small"><i class="bi bi-diagram-2"></i> Workflow Builder</div>
            <div class="list-group list-group-flush small">
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('workflow.flow-definition.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('workflow.flow-definition.index')); ?>">Flow Definition</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('workflow.routing-rule.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('workflow.routing-rule.index')); ?>">Routing Rule</a>
            </div>
        </div>
    </aside>
    <div class="col-lg-9 col-xl-10">
        <?php echo $__env->yieldContent('workflow_content'); ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/layouts/workflow.blade.php ENDPATH**/ ?>