<?php echo $__env->make('partials._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Flow Code <span class="text-danger">*</span></label>
        <input type="text" name="flow_code" required maxlength="80" class="form-control"
               value="<?php echo e(old('flow_code', $item->flow_code ?? '')); ?>" <?php echo e(isset($item)?'readonly':''); ?>>
    </div>
    <div class="col-md-8">
        <label class="form-label">Flow Name <span class="text-danger">*</span></label>
        <input type="text" name="flow_name" required maxlength="180" class="form-control"
               value="<?php echo e(old('flow_name', $item->flow_name ?? '')); ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Source App <span class="text-danger">*</span></label>
        <select name="idtblsource_app" class="form-select" required <?php echo e(isset($item)?'disabled':''); ?>>
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sa->idtblsource_app); ?>"
                    <?php echo e((string)old('idtblsource_app',$item->idtblsource_app??'')===(string)$sa->idtblsource_app?'selected':''); ?>>
                    <?php echo e($sa->app_code); ?> &mdash; <?php echo e($sa->app_name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if(isset($item)): ?><input type="hidden" name="idtblsource_app" value="<?php echo e($item->idtblsource_app); ?>"><?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label">Document Type <span class="text-danger">*</span></label>
        <select name="idtbldocument_type" class="form-select" required <?php echo e(isset($item)?'disabled':''); ?>>
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $documentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($d->idtbldocument_type); ?>"
                    <?php echo e((string)old('idtbldocument_type',$item->idtbldocument_type??'')===(string)$d->idtbldocument_type?'selected':''); ?>>
                    <?php echo e(optional($d->sourceApp)->app_code); ?> / <?php echo e($d->doc_code); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if(isset($item)): ?><input type="hidden" name="idtbldocument_type" value="<?php echo e($item->idtbldocument_type); ?>"><?php endif; ?>
    </div>
    <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?php echo e(old('description', $item->description ?? '')); ?></textarea></div>
    <div class="col-md-6"><div class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
               <?php echo e(old('is_active',$item->is_active??true)?'checked':''); ?>>
        <label for="ia" class="form-check-label">Aktif</label>
    </div></div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="<?php echo e(route('workflow.flow-definition.index')); ?>" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
<?php /**PATH /var/www/html/approval_center/resources/views/workflow/flow_definition/_form.blade.php ENDPATH**/ ?>