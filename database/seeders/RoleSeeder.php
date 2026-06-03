<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Memastikan 4 role utama selalu ada di tblrole.
 *
 * Idempotent: pakai INSERT IGNORE / ON DUPLICATE KEY agar aman dijalankan
 * berulang. SQL schema utama sudah berisi INSERT untuk role-role ini,
 * seeder hanya sebagai jaring pengaman jika DB di-reset.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now('Asia/Jakarta')->format('Y-m-d H:i:s.v');

        $roles = [
            [
                'role_code'   => 'ADMIN_APPROVAL',
                'role_name'   => 'Admin Approval Center',
                'description' => 'Mengelola master flow, rule, dan monitoring approval.',
            ],
            [
                'role_code'   => 'APPROVER',
                'role_name'   => 'Approver',
                'description' => 'Pejabat yang melakukan approve / reject / return task.',
            ],
            [
                'role_code'   => 'REQUESTER',
                'role_name'   => 'Requester',
                'description' => 'Pembuat permohonan dari aplikasi asal. Hanya melihat request miliknya.',
            ],
            [
                'role_code'   => 'AUDITOR',
                'role_name'   => 'Auditor',
                'description' => 'Read-only untuk monitoring dan audit trail approval.',
            ],
        ];

        foreach ($roles as $r) {
            DB::statement(
                "INSERT INTO tblrole (role_code, role_name, description, is_active, created_at)
                 VALUES (?, ?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE
                    role_name = VALUES(role_name),
                    description = VALUES(description),
                    updated_at = VALUES(created_at)",
                [$r['role_code'], $r['role_name'], $r['description'], $now]
            );
        }
    }
}
