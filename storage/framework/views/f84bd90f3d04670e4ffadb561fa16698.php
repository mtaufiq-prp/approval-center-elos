<?php $__env->startSection('title', 'API Client'); ?>

<?php $__env->startSection('master_content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-key"></i> API Client</h5>
    <a href="<?php echo e(route('master.api-client.create')); ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Tambah
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                       class="form-control form-control-sm" placeholder="Cari client_key / app_code...">
            </div>
            <div class="col-md-3">
                <select name="idtblsource_app" class="form-select form-select-sm">
                    <option value="">Semua source app</option>
                    <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($sa->idtblsource_app); ?>"
                            <?php echo e((string) request('idtblsource_app') === (string) $sa->idtblsource_app ? 'selected' : ''); ?>>
                            <?php echo e($sa->app_code); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="is_active" class="form-select form-select-sm">
                    <option value="all">Semua status</option>
                    <option value="1" <?php echo e(request('is_active') === '1' ? 'selected' : ''); ?>>Aktif</option>
                    <option value="0" <?php echo e(request('is_active') === '0' ? 'selected' : ''); ?>>Revoked</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
        </form>

        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle"></i>
            Plaintext secret hanya ditampilkan satu kali saat dibuat / di-rotate.
            Jika lupa, gunakan tombol <strong>Rotate Secret</strong> untuk membuat secret baru.
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>Source App</th>
                        <th>Client Key</th>
                        <th>Allowed IP</th>
                        <th>Last Used</th>
                        <th>Secret Rotated</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e(optional($item->sourceApp)->app_code); ?></td>
                            <td><code class="small"><?php echo e($item->client_key); ?></code></td>
                            <td class="text-muted"><?php echo e($item->allowed_ip ?: '-'); ?></td>
                            <td class="text-muted"><?php echo e(optional($item->last_used_at)->format('Y-m-d H:i') ?? '-'); ?></td>
                            <td class="text-muted"><?php echo e(optional($item->secret_rotated_at)->format('Y-m-d H:i') ?? '-'); ?></td>
                            <td>
                                <?php if($item->is_active): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Revoked</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo e(route('master.api-client.edit', $item->idtblapi_client)); ?>"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="<?php echo e(route('master.api-client.rotate', $item->idtblapi_client)); ?>"
                                      class="d-inline" data-confirm="Rotate secret untuk <?php echo e($item->client_key); ?>? Secret lama tidak akan bisa dipakai lagi.">
                                    <?php echo csrf_field(); ?>
                                    <button class="btn btn-sm btn-outline-warning">Rotate</button>
                                </form>
                                <form method="POST" action="<?php echo e(route('master.api-client.destroy', $item->idtblapi_client)); ?>"
                                      class="d-inline" data-confirm="Yakin <?php echo e($item->is_active ? 'revoke' : 'aktifkan kembali'); ?> <?php echo e($item->client_key); ?>?">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button class="btn btn-sm btn-outline-<?php echo e($item->is_active ? 'danger' : 'success'); ?>">
                                        <?php echo e($item->is_active ? 'Revoke' : 'Activate'); ?>

                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end"><?php echo e($items->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/approval_center/resources/views/master/api_client/index.blade.php ENDPATH**/ ?>