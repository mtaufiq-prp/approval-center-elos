<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Approval Center'); ?> — Propan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; font-size:.9rem; }
        .navbar-brand { font-weight:700; letter-spacing:.02em; }
        .main-content { padding:1.25rem 1rem; }
        .sidebar-sticky { position:sticky; top:1rem; }
        .badge-count { font-size:.7em; }
        .table td,.table th { vertical-align:middle; }
        .status-pill { display:inline-block; padding:.2em .6em; border-radius:99px; font-size:.75em; font-weight:600; }
    </style>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background:#1a3a5e;">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo e(route('home')); ?>">
            <i class="bi bi-shield-check text-warning"></i> Approval Center
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo e(request()->routeIs('home','dashboard') ? 'active' : ''); ?>"
                       href="<?php echo e(route('home')); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <?php if(auth()->guard()->check()): ?>
                    <?php if(auth()->user()->hasAnyRole('APPROVER','ADMIN_APPROVAL')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('inbox.*') ? 'active' : ''); ?>"
                           href="<?php echo e(route('inbox.index')); ?>">
                            <i class="bi bi-inbox"></i> Inbox
                            <?php if(isset($inboxCount) && $inboxCount > 0): ?>
                                <span class="badge bg-danger badge-count"><?php echo e($inboxCount); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasAnyRole('ADMIN_APPROVAL','AUDITOR')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo e(request()->routeIs('monitoring.*','audit.*') ? 'active' : ''); ?>"
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-graph-up"></i> Monitor
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo e(route('monitoring.index')); ?>">
                                <i class="bi bi-list-check"></i> Approval Request</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('audit.action-log')); ?>">
                                <i class="bi bi-journal-text"></i> Action Log</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('audit.audit-event')); ?>">
                                <i class="bi bi-shield-exclamation"></i> Audit Event</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('audit.integration-log')); ?>">
                                <i class="bi bi-arrow-left-right"></i> Integration Log</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('audit.callback-outbox')); ?>">
                                <i class="bi bi-send-check"></i> Callback Outbox</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasAnyRole('ADMIN_APPROVAL')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo e(request()->routeIs('master.*') ? 'active' : ''); ?>"
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Master
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo e(route('master.source-app.index')); ?>">Source App</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.api-client.index')); ?>">API Client</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.user.index')); ?>">User</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.role.index')); ?>">Role</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.org-unit.index')); ?>">Org Unit</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.position.index')); ?>">Position</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.approval-group.index')); ?>">Approval Group</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.document-type.index')); ?>">Document Type</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(route('master.delegation.index')); ?>">Delegation</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('workflow.*') ? 'active' : ''); ?>"
                           href="<?php echo e(route('workflow.flow-definition.index')); ?>">
                            <i class="bi bi-diagram-3"></i> Workflow
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if(auth()->guard()->check()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <span class="fw-semibold"><?php echo e(auth()->user()->user_ref); ?></span>
                        <span class="opacity-75">— <?php echo e(auth()->user()->full_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo e(route('password.change')); ?>">
                            <i class="bi bi-key"></i> Ganti Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid main-content">
    
    <?php $__currentLoopData = ['status' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(session($key)): ?>
            <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show mb-3" role="alert">
                <?php echo nl2br(e(session($key))); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php echo $__env->yieldContent('content'); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Confirm forms
    document.querySelectorAll('form[data-confirm]').forEach(f => {
        f.addEventListener('submit', e => {
            if (!confirm(f.dataset.confirm)) e.preventDefault();
        });
    });
    // Auto-dismiss alerts after 6s
    setTimeout(() => {
        document.querySelectorAll('.alert.alert-success').forEach(el => {
            let a = bootstrap.Alert.getOrCreateInstance(el); if(a) a.close();
        });
    }, 6000);
});
</script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/approval_center/resources/views/layouts/app.blade.php ENDPATH**/ ?>