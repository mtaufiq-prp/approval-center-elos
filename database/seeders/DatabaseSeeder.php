<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder untuk lingkungan development.
     *
     * Urutan:
     *  1. RoleSeeder         - pastikan 4 role utama ada (idempotent).
     *  2. DevAdminUserSeeder - bikin user admin dev untuk login pertama.
     *
     * Untuk production, JANGAN jalankan DevAdminUserSeeder.
     * Gunakan command artisan khusus / set password manual oleh DBA.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DevAdminUserSeeder::class,
        ]);
    }
}
