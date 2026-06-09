<?php

use Illuminate\Support\Facades\Route; 

/*
|--------------------------------------------------------------------------
| Web Routes - Approval Center
|--------------------------------------------------------------------------
| Diaktifkan:
|   Tahap 4  — Auth: login/logout/change-password
|   Tahap 5A — Master Data CRUD
|   Tahap 5B — Workflow Builder CRUD + Validate + Deploy + Preview + Clone
|   Tahap 8  — Inbox, Approval Action, Monitoring, Audit, Callback
*/

// ============================================================
// PUBLIK (read-only, token HMAC) — tracking dari source app tanpa login
// ============================================================
Route::get('/track/{id}', [\App\Http\Controllers\Web\TrackController::class, 'show'])
    ->whereNumber('id')->name('track.show');

// ============================================================
// GUEST
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login',  [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'login']);
});

// ============================================================
// AUTHENTICATED
// ============================================================
Route::middleware(['auth'])->group(function () {

    Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

    Route::get ('/change-password', [\App\Http\Controllers\Auth\ChangePasswordController::class, 'show'  ])->name('password.change');
    Route::post('/change-password', [\App\Http\Controllers\Auth\ChangePasswordController::class, 'update']);

    Route::middleware(['force_password_change'])->group(function () {

        Route::get('/',          [\App\Http\Controllers\Web\DashboardController::class, 'index'])->name('home');
        Route::get('/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])->name('dashboard');

        // ============================================================
        // TAHAP 5A — MASTER DATA (Admin only)
        // ============================================================
        Route::middleware('role:ADMIN_APPROVAL')->prefix('master')->name('master.')->group(function () {

            Route::resource('source-app', \App\Http\Controllers\Web\Master\SourceAppController::class)
                ->parameters(['source-app' => 'source_app'])->except('show');

            Route::resource('api-client', \App\Http\Controllers\Web\Master\ApiClientController::class)
                ->parameters(['api-client' => 'api_client'])->except('show');
            Route::get ('api-client/{idtblapi_client}/secret',
                [\App\Http\Controllers\Web\Master\ApiClientController::class, 'showSecret'])
                ->whereNumber('idtblapi_client')->name('api-client.show-secret');
            Route::post('api-client/{api_client}/rotate',
                [\App\Http\Controllers\Web\Master\ApiClientController::class, 'rotateSecret'])
                ->name('api-client.rotate');

            Route::resource('user', \App\Http\Controllers\Web\Master\UserController::class)->except('show');
            Route::post('user/{user}/reset-password',
                [\App\Http\Controllers\Web\Master\UserController::class, 'resetPassword'])
                ->name('user.reset-password');

            Route::resource('role', \App\Http\Controllers\Web\Master\RoleController::class)->except('show');

            Route::resource('org-unit', \App\Http\Controllers\Web\Master\OrgUnitController::class)
                ->parameters(['org-unit' => 'org_unit'])->except('show');

            Route::resource('position', \App\Http\Controllers\Web\Master\PositionController::class)->except('show');

            Route::resource('approval-group', \App\Http\Controllers\Web\Master\ApprovalGroupController::class)
                ->parameters(['approval-group' => 'approval_group'])->except('show');
            Route::post  ('approval-group/{approval_group}/member',
                [\App\Http\Controllers\Web\Master\ApprovalGroupController::class, 'addMember'])
                ->name('approval-group.add-member');
            Route::delete('approval-group/{approval_group}/member/{idtblapproval_group_member}',
                [\App\Http\Controllers\Web\Master\ApprovalGroupController::class, 'removeMember'])
                ->whereNumber('idtblapproval_group_member')
                ->name('approval-group.remove-member');

            Route::resource('document-type', \App\Http\Controllers\Web\Master\DocumentTypeController::class)
                ->parameters(['document-type' => 'document_type'])->except('show');

            Route::resource('delegation', \App\Http\Controllers\Web\Master\DelegationController::class)->except('show');
        });

        // ============================================================
        // TAHAP 5B — WORKFLOW BUILDER (Admin only)
        // ============================================================
        Route::middleware('role:ADMIN_APPROVAL')->prefix('workflow')->name('workflow.')->group(function () {

            Route::resource('flow-definition', \App\Http\Controllers\Web\Workflow\FlowDefinitionController::class)
                ->parameters(['flow-definition' => 'flow_definition'])->except('show');

            // Flow Version (eksplisit, bukan resource karena nested + custom actions)
            Route::get   ('flow-definition/{flow_definition}/version',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'index'])->name('flow-version.index');
            Route::get   ('flow-definition/{flow_definition}/version/create',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'create'])->name('flow-version.create');
            Route::post  ('flow-definition/{flow_definition}/version',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'store'])->name('flow-version.store');
            Route::get   ('flow-version/{flow_version}',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'show'])->name('flow-version.show');
            Route::get   ('flow-version/{flow_version}/edit',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'edit'])->name('flow-version.edit');
            Route::put   ('flow-version/{flow_version}',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'update'])->name('flow-version.update');
            Route::post  ('flow-version/{flow_version}/validate',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'runValidation'])->name('flow-version.validate');
            Route::post  ('flow-version/{flow_version}/deploy',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'deploy'])->name('flow-version.deploy');
            Route::post  ('flow-version/{flow_version}/clone',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'cloneVersion'])->name('flow-version.clone');
            Route::get   ('flow-version/{flow_version}/preview',
                [\App\Http\Controllers\Web\Workflow\FlowVersionController::class, 'preview'])->name('flow-version.preview');

            // Flow Node (per version)
            Route::get   ('flow-version/{flow_version}/node',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'index'])->name('flow-node.index');
            Route::get   ('flow-version/{flow_version}/node/create',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'create'])->name('flow-node.create');
            Route::post  ('flow-version/{flow_version}/node',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'store'])->name('flow-node.store');
            Route::get   ('flow-version/{flow_version}/node/{flow_step}/edit',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'edit'])->name('flow-node.edit');
            Route::put   ('flow-version/{flow_version}/node/{flow_step}',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'update'])->name('flow-node.update');
            Route::delete('flow-version/{flow_version}/node/{flow_step}',
                [\App\Http\Controllers\Web\Workflow\FlowNodeController::class, 'destroy'])->name('flow-node.destroy');

            // Assignee Rule (per node)
            Route::get   ('flow-version/{flow_version}/node/{flow_step}/assignee-rule/create',
                [\App\Http\Controllers\Web\Workflow\StepAssigneeRuleController::class, 'create'])->name('assignee-rule.create');
            Route::post  ('flow-version/{flow_version}/node/{flow_step}/assignee-rule',
                [\App\Http\Controllers\Web\Workflow\StepAssigneeRuleController::class, 'store'])->name('assignee-rule.store');
            Route::get   ('flow-version/{flow_version}/node/{flow_step}/assignee-rule/{assignee_rule}/edit',
                [\App\Http\Controllers\Web\Workflow\StepAssigneeRuleController::class, 'edit'])->name('assignee-rule.edit');
            Route::put   ('flow-version/{flow_version}/node/{flow_step}/assignee-rule/{assignee_rule}',
                [\App\Http\Controllers\Web\Workflow\StepAssigneeRuleController::class, 'update'])->name('assignee-rule.update');
            Route::delete('flow-version/{flow_version}/node/{flow_step}/assignee-rule/{assignee_rule}',
                [\App\Http\Controllers\Web\Workflow\StepAssigneeRuleController::class, 'destroy'])->name('assignee-rule.destroy');

            // Flow Edge (per version)
            Route::get   ('flow-version/{flow_version}/edge',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'index'])->name('flow-edge.index');
            Route::get   ('flow-version/{flow_version}/edge/create',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'create'])->name('flow-edge.create');
            Route::post  ('flow-version/{flow_version}/edge',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'store'])->name('flow-edge.store');
            Route::get   ('flow-version/{flow_version}/edge/{flow_transition}/edit',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'edit'])->name('flow-edge.edit');
            Route::put   ('flow-version/{flow_version}/edge/{flow_transition}',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'update'])->name('flow-edge.update');
            Route::delete('flow-version/{flow_version}/edge/{flow_transition}',
                [\App\Http\Controllers\Web\Workflow\FlowEdgeController::class, 'destroy'])->name('flow-edge.destroy');

            // Routing Rule
            Route::resource('routing-rule', \App\Http\Controllers\Web\Workflow\RoutingRuleController::class)
                ->parameters(['routing-rule' => 'routing_rule'])->except('show');

            // ============================================================
            // VISUAL WORKFLOW BUILDER (React Flow canvas)
            // ============================================================
            // Halaman builder
            Route::get('flow-version/{flow_version}/builder',
                [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builder'])
                ->name('flow-version.builder');

            // JSON API endpoints (pakai session auth, bukan HMAC)
            Route::prefix('api')->name('builder-api.')->group(function () {
                Route::get ('flow-version/{flow_version}/builder-data',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builderData'])
                    ->name('data');
                Route::post('flow-version/{flow_version}/builder-save',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builderSave'])
                    ->name('save');
                Route::post('flow-version/{flow_version}/builder-validate',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builderValidate'])
                    ->name('validate');
                Route::post('flow-version/{flow_version}/builder-deploy',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builderDeploy'])
                    ->name('deploy');
                Route::post('flow-version/{flow_version}/builder-clone',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'builderClone'])
                    ->name('clone');
                // Lookup jobtitle untuk assignee rule type JOBTITLE
                Route::get('jobtitle-search',
                    [\App\Http\Controllers\Web\Workflow\FlowBuilderController::class, 'jobtitleSearch'])
                    ->name('jobtitle-search');
            });
        });

        // ============================================================
        // TAHAP 8 — INBOX, APPROVAL ACTION, MONITORING, AUDIT
        // ============================================================
        Route::middleware('role:APPROVER,ADMIN_APPROVAL')->group(function () {
            Route::get ('/inbox', [\App\Http\Controllers\Web\InboxController::class, 'index'])->name('inbox.index');
            Route::get ('/inbox/history', [\App\Http\Controllers\Web\InboxController::class, 'history'])->name('inbox.history');
            Route::get ('/inbox/task/{task}', [\App\Http\Controllers\Web\InboxController::class, 'show'])->name('inbox.show');
            Route::post('/inbox/task/{task}/action', [\App\Http\Controllers\Web\InboxController::class, 'act'])->name('inbox.act');
        });

        Route::middleware('role:ADMIN_APPROVAL,AUDITOR')->prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('/',                            [\App\Http\Controllers\Web\MonitoringController::class, 'index'])->name('index');
            Route::get('/request/{approval_request}', [\App\Http\Controllers\Web\MonitoringController::class, 'show'])->name('show');
        });

        Route::middleware('role:ADMIN_APPROVAL,AUDITOR')->prefix('audit')->name('audit.')->group(function () {
            Route::get('/action-log',       [\App\Http\Controllers\Web\AuditController::class, 'actionLog'])->name('action-log');
            Route::get('/audit-event',      [\App\Http\Controllers\Web\AuditController::class, 'auditEvent'])->name('audit-event');
            Route::get('/integration-log',  [\App\Http\Controllers\Web\AuditController::class, 'integrationLog'])->name('integration-log');
            Route::get('/callback-outbox',  [\App\Http\Controllers\Web\AuditController::class, 'callbackOutbox'])->name('callback-outbox');
            Route::post('/callback-outbox/{idtblcallback_outbox}/retry',
                [\App\Http\Controllers\Web\AuditController::class, 'retryCallback'])
                ->whereNumber('idtblcallback_outbox')->name('callback-outbox.retry');
        });
    });
});
