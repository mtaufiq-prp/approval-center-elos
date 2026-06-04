<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat user admin awal untuk development.
 *
 * PENTING:
 * - Default password "admin123" dengan must_change_password = 1.
 *   User WAJIB ganti password saat login pertama (middleware
 *   ForcePasswordChange akan menahan).
 * - Seeder ini HANYA untuk environment local / development.
 *   Untuk production, gunakan command artisan terpisah / set manual.
 * - Seeder idempotent: ON DUPLICATE KEY UPDATE memastikan tidak duplikat
 *   walaupun dijalankan ulang.
 */
class DevAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('DevAdminUserSeeder dilewati di environment production.');
            return;
        }

        $now      = now('Asia/Jakarta')->format('Y-m-d H:i:s.v');
        $rawPass  = bin2hex(random_bytes(12)); // random 24-char hex, ditampilkan sekali
        $password = Hash::make($rawPass);

        // 1. Insert / update user admin
        DB::statement(
            "INSERT INTO tbluser
                (user_ref, full_name, email, password, must_change_password, is_active, created_at)
             VALUES (?, ?, ?, ?, 1, 1, ?)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                password = VALUES(password),
                must_change_password = VALUES(must_change_password),
                is_active = 1,
                updated_at = VALUES(created_at)",
            ['ADMIN_DEV', 'Administrator Development', 'admin.dev@propan.co.id', $password, $now]
        );

        // 2. Ambil id user admin
        $userId = DB::table('tbluser')->where('user_ref', 'ADMIN_DEV')->value('idtbluser');
        if (! $userId) {
            $this->command->error('Gagal menemukan user ADMIN_DEV setelah insert.');
            return;
        }

        // 3. Pastikan ada role ADMIN_APPROVAL (RoleSeeder seharusnya sudah berjalan)
        $roleId = DB::table('tblrole')->where('role_code', 'ADMIN_APPROVAL')->value('idtblrole');
        if (! $roleId) {
            $this->command->error('Role ADMIN_APPROVAL belum ada. Jalankan RoleSeeder dulu.');
            return;
        }

        // 4. Mapping user-role (idempotent via unique key uq_tbl_user_role)
        DB::statement(
            "INSERT INTO tbluser_role (idtbluser, idtblrole, created_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE idtbluser = VALUES(idtbluser)",
            [$userId, $roleId, $now]
        );

        $this->command->info("Admin dev siap: user_ref=ADMIN_DEV password={$rawPass} (wajib ganti — catat sebelum jendela ini ditutup).");
    }
}
