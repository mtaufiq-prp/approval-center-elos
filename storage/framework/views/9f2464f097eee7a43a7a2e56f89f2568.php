<?php $__env->startSection('title','Routing Rule'); ?>
<?php $__env->startSection('workflow_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-signpost-split"></i> Routing Rule</h5>
    <a href="<?php echo e(route('workflow.routing-rule.create')); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control form-control-sm" placeholder="Cari rule_code..."></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($sa->idtblsource_app); ?>" <?php echo e((string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':''); ?>><?php echo e($sa->app_code); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="alert alert-info small"><i class="bi bi-info-circle"></i>
        Routing Rule menentukan <strong>flow mana</strong> yang dipakai saat approval request masuk berdasarkan
        source_app, document_type, dan condition_json. Rule dengan <code>priority_no</code> terkecil dievaluasi pertama.
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Priority</th><th>Rule Code</th><th>App</th><th>Doc</th><th>Flow</th><th>Version Override</th><th>Condition</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><strong><?php echo e($r->priority_no); ?></strong></td>
                    <td><code><?php echo e($r->rule_code); ?></code></td>
                    <td><?php echo e(optional($r->sourceApp)->app_code); ?></td>
                    <td><?php echo e(optional($r->documentType)->doc_code); ?></td>
                    <td><code><?php echo e(optional($r->flowDefinition)->flow_code); ?></code></td>
                    <td><?php echo e($r->idtblflow_version ? 'v'.optional($r->flowVersion)->version_no : '<em>ACTIVE auto</em>'); ?></td>
                    <td class="text-muted small"><?php echo e(\Str::limit(json_encode($r->condition_json), 60)); ?></td>
                    <td><?php if($r->is_active): ?><span class="badge bg-success">Aktif</span><?php else: ?><span class="badge bg-secondary">Nonaktif</span><?php endif; ?></td>
                    <td class="text-end">
                        <a href="<?php echo e(route('workflow.routing-rule.edit', $r->idtblrouting_rule)); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="<?php echo e(route('workflow.routing-rule.destroy', $r->idtblrouting_rule)); ?>" class="d-inline" data-confirm="Yakin?">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="btn btn-sm btn-outline-<?php echo e($r->is_active?'danger':'success'); ?>"><?php echo e($r->is_active?'Deactivate':'Activate'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <tr><td colspan="9" class="text-center text-muted py-4">Belum ada routing rule.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/workflow/routing_rule/index.blade.php ENDPATH**/ ?>