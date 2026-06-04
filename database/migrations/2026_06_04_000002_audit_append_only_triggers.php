<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #90 Jadikan tblaudit_event & tblaction_log append-only (tamper-evident)
 * via trigger BEFORE UPDATE/DELETE yang SIGNAL error. Audit hanya boleh INSERT.
 *
 * Catatan: trigger butuh hak TRIGGER pada DB user. Jika tidak tersedia,
 * alternatifnya batasi privilege DB user ke INSERT/SELECT pada kedua tabel.
 */
return new class extends Migration
{
    private array $tables = ['tblaudit_event', 'tblaction_log'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            foreach (['update', 'delete'] as $op) {
                $trg = "trg_{$t}_no_{$op}";
                DB::unprepared("DROP TRIGGER IF EXISTS {$trg}");
                DB::unprepared(
                    "CREATE TRIGGER {$trg} BEFORE " . strtoupper($op) . " ON {$t}
                     FOR EACH ROW
                     BEGIN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Audit log bersifat append-only: {$op} tidak diizinkan.';
                     END"
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            foreach (['update', 'delete'] as $op) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$t}_no_{$op}");
            }
        }
    }
};
