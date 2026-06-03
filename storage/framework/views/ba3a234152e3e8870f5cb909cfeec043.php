<?php echo $__env->make('partials._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">User Ref <span class="text-danger">*</span></label>
        <input type="text" name="user_ref" required maxlength="80" class="form-control"
               value="<?php echo e(old('user_ref', $item->user_ref ?? '')); ?>"
               <?php echo e(isset($item) ? 'readonly' : ''); ?>>
        <small class="text-muted">NPK / employee id. Tidak dapat diubah setelah dibuat.</small>
    </div>
    <div class="col-md-8">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="full_name" required maxlength="150" class="form-control"
               value="<?php echo e(old('full_name', $item->full_name ?? '')); ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" maxlength="150" class="form-control"
               value="<?php echo e(old('email', $item->email ?? '')); ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" maxlength="50" class="form-control"
               value="<?php echo e(old('phone', $item->phone ?? '')); ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Org Unit</label>
        <select name="idtblorg_unit" class="form-select">
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $orgUnits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($o->idtblorg_unit); ?>"
                    <?php echo e((string) old('idtblorg_unit', $item->idtblorg_unit ?? '') === (string) $o->idtblorg_unit ? 'selected' : ''); ?>>
                    <?php echo e($o->org_code); ?> — <?php echo e($o->org_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Position</label>
        <select name="idtblposition" class="form-select">
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $positions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($p->idtblposition); ?>"
                    <?php echo e((string) old('idtblposition', $item->idtblposition ?? '') === (string) $p->idtblposition ? 'selected' : ''); ?>>
                    <?php echo e($p->position_code); ?> — <?php echo e($p->position_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Atasan (Superior)</label>
        <select name="idtbluser_superior" class="form-select">
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $superiors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(isset($item) && $s->idtbluser === $item->idtbluser) continue; ?>
                <option value="<?php echo e($s->idtbluser); ?>"
                    <?php echo e((string) old('idtbluser_superior', $item->idtbluser_superior ?? '') === (string) $s->idtbluser ? 'selected' : ''); ?>>
                    <?php echo e($s->user_ref); ?> — <?php echo e($s->full_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Roles</label>
        <div class="row">
            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $assigned = collect(old('role_ids', isset($item) ? $item->roles->pluck('idtblrole')->toArray() : []))
                        ->map(fn($v) => (int) $v)->all();
                ?>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" name="role_ids[]" value="<?php echo e($r->idtblrole); ?>"
                               id="role_<?php echo e($r->idtblrole); ?>"
                               class="form-check-input"
                               <?php echo e(in_array($r->idtblrole, $assigned, true) ? 'checked' : ''); ?>>
                        <label for="role_<?php echo e($r->idtblrole); ?>" class="form-check-label">
                            <code><?php echo e($r->role_code); ?></code>
                        </label>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                   <?php echo e(old('is_active', $item->is_active ?? true) ? 'checked' : ''); ?>>
            <label for="ia" class="form-check-label">Aktif</label>
        </div>
    </div>
</div>
<hr>
<div class="d-flex justify-content-between">
    <div>
        <?php if(isset($item)): ?>
            <form method="POST" action="<?php echo e(route('master.user.reset-password', $item->idtbluser)); ?>"
                  class="d-inline" data-confirm="Reset password untuk <?php echo e($item->user_ref); ?>?">
                <?php echo csrf_field(); ?>
                <button class="btn btn-outline-warning"><i class="bi bi-key"></i> Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
    <div>
        <a href="<?php echo e(route('master.user.index')); ?>" class="btn btn-light me-2">Batal</a>
        <button class="btn btn-primary">Simpan</button>
    </div>
</div>
<?php /**PATH /var/www/html/approval_center/resources/views/master/user/_form.blade.php ENDPATH**/ ?>