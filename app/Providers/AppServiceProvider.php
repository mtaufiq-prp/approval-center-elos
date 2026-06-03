<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider
 *
 * Booting umum. Pada tahap awal kosong saja; akan diisi binding
 * service-service di Tahap 6 dan 7.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register WorkflowServiceProvider akan dilakukan di Tahap 6.
    }

    public function boot(): void
    {
        // Kosong dulu. Tahap 8 mungkin menambahkan Blade::if() helper
        // dan view composer untuk badge jumlah inbox.
    }
}
