<?php

/*
|--------------------------------------------------------------------------
| Service Providers - Approval Center
|--------------------------------------------------------------------------
| Laravel 11 mendaftarkan service provider via array di file ini.
| Provider Laravel default (Foundation) ter-register otomatis.
*/

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // WorkflowServiceProvider akan ditambahkan di Tahap 6
];
