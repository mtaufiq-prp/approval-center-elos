<?php $__env->startSection('title', 'Edit User'); ?>
<?php $__env->startSection('master_content'); ?>
<h5 class="mb-3"><i class="bi bi-people"></i> Edit User: <?php echo e($item->user_ref); ?></h5>

<?php if(session('temp_password')): ?>
<div class="alert alert-danger">
    <i class="bi bi-shield-lock"></i>
    <strong>Password sementara untuk <?php echo e(session('reset_user_ref')); ?></strong> (tampil sekali):
    <div class="input-group mt-2">
        <input type="text" id="tpw" class="form-control font-monospace" value="<?php echo e(session('temp_password')); ?>" readonly>
        <button class="btn btn-outline-secondary" type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('tpw').value)">
            <i class="bi bi-clipboard"></i> Copy
        </button>
    </div>
    <small class="d-block mt-2">User wajib ganti password saat login pertama.</small>
</div>
<?php endif; ?>

<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="<?php echo e(route('master.user.update', $item->idtbluser)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <?php echo $__env->make('master.user._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </form>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/user/edit.blade.php ENDPATH**/ ?>