<?php $__env->startPush('scripts'); ?>
<script>
    // Confirm deactivate
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-confirm]').forEach(f => {
            f.addEventListener('submit', e => {
                if (!confirm(f.dataset.confirm)) e.preventDefault();
            });
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <aside class="col-lg-3 col-xl-2 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold small">
                <i class="bi bi-gear"></i> Master Data
            </div>
            <div class="list-group list-group-flush small">
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.source-app.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.source-app.index')); ?>">Source App</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.api-client.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.api-client.index')); ?>">API Client</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.user.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.user.index')); ?>">User</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.role.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.role.index')); ?>">Role</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.org-unit.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.org-unit.index')); ?>">Org Unit</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.position.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.position.index')); ?>">Position</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.approval-group.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.approval-group.index')); ?>">Approval Group</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.document-type.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.document-type.index')); ?>">Document Type</a>
                <a class="list-group-item list-group-item-action <?php echo e(request()->routeIs('master.delegation.*') ? 'active' : ''); ?>"
                   href="<?php echo e(route('master.delegation.index')); ?>">Delegation</a>
            </div>
        </div>
    </aside>

    <div class="col-lg-9 col-xl-10">
        <?php echo $__env->yieldContent('master_content'); ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/layouts/master.blade.php ENDPATH**/ ?>