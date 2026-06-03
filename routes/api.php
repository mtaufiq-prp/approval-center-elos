<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Approval Center
|--------------------------------------------------------------------------
| Semua route /api/v1/* dilindungi middleware api_client_auth (HMAC SHA256).
| Urutan middleware:
|   api_client_auth → verifikasi X-Client-Key + X-Timestamp + X-Signature
|
| Catatan: ApiClientVerifyHmac digabung ke ApiClientAuthenticate
|          (Tahap 7 implementasi penuh).
*/

Route::prefix('v1')->middleware('api_client_auth')->group(function () {

    // Submit approval request
    Route::post('/approval/submit',
        \App\Http\Controllers\Api\V1\ApprovalSubmitController::class);

    // Status inquiry
    Route::get('/approval/status',
        \App\Http\Controllers\Api\V1\ApprovalStatusController::class);
    Route::get('/approval/{approval_request_id}/status',
        \App\Http\Controllers\Api\V1\ApprovalStatusController::class)
        ->whereNumber('approval_request_id');

    // Cancel
    Route::post('/approval/{approval_request_id}/cancel',
        \App\Http\Controllers\Api\V1\ApprovalCancelController::class)
        ->whereNumber('approval_request_id');

    // Callback test (untuk integrasi dev)
    Route::post('/callback/test',
        \App\Http\Controllers\Api\V1\CallbackTestController::class);
});
