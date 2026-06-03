<?php echo $__env->make('partials._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">App Code <span class="text-danger">*</span></label>
        <input type="text" name="app_code" required maxlength="50"
               class="form-control text-uppercase"
               value="<?php echo e(old('app_code', $item->app_code ?? '')); ?>"
               <?php echo e(isset($item) ? 'readonly' : ''); ?>>
        <small class="text-muted">Huruf kapital, angka, underscore. Tidak dapat diubah setelah dibuat.</small>
    </div>
    <div class="col-md-8">
        <label class="form-label">App Name <span class="text-danger">*</span></label>
        <input type="text" name="app_name" required maxlength="150"
               class="form-control" value="<?php echo e(old('app_name', $item->app_name ?? '')); ?>">
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2" maxlength="1000"><?php echo e(old('description', $item->description ?? '')); ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Base URL</label>
        <input type="url" name="base_url" class="form-control" maxlength="255"
               value="<?php echo e(old('base_url', $item->base_url ?? '')); ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Default Callback URL</label>
        <input type="url" name="default_callback_url" class="form-control" maxlength="255"
               value="<?php echo e(old('default_callback_url', $item->default_callback_url ?? '')); ?>">
        <small class="text-muted">Fallback callback URL jika request tidak mengirimkan callback_url.</small>
    </div>
    <div class="col-md-6">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input"
                   <?php echo e(old('is_active', $item->is_active ?? true) ? 'checked' : ''); ?>>
            <label for="is_active" class="form-check-label">Aktif</label>
        </div>
    </div>
</div>

<hr>
<div class="d-flex justify-content-end">
    <a href="<?php echo e(route('master.source-app.index')); ?>" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
<?php /**PATH /var/www/html/approval_center/resources/views/master/source_app/_form.blade.php ENDPATH**/ ?>