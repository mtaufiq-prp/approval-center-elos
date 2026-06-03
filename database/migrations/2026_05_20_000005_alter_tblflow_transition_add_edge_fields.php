<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BPMN-lite: ALTER tblflow_transition menjadi Edge antar node.
 *
 * Fix: semua kolom ditambahkan via DB::statement raw agar
 * urutan AFTER bisa dijamin dan tidak ada dependency issue
 * dalam satu Schema::table() closure.
 *
 * Idempotent: cek hasColumn() sebelum setiap ADD COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. transition_code
        if (! Schema::hasColumn('tblflow_transition', 'transition_code')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN transition_code VARCHAR(100) NULL
                COMMENT 'Kode unik edge dalam satu flow_version. Mis: BMH_TO_RRM.'
                AFTER idtblflow_version");
        }

        // 2. transition_name
        if (! Schema::hasColumn('tblflow_transition', 'transition_name')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN transition_name VARCHAR(150) NULL
                COMMENT 'Nama edge yang readable.'
                AFTER transition_code");
        }

        // 3. transition_type  — AFTER transition_name (sudah ada di step 2)
        if (! Schema::hasColumn('tblflow_transition', 'transition_type')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN transition_type ENUM('NORMAL','DEFAULT','ERROR','TIMEOUT')
                NOT NULL DEFAULT 'NORMAL'
                COMMENT 'Jenis edge.'
                AFTER transition_name");
        }

        // 4. priority_no
        if (! Schema::hasColumn('tblflow_transition', 'priority_no')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN priority_no INT NOT NULL DEFAULT 0
                COMMENT 'Prioritas evaluasi edge untuk EXCLUSIVE gateway. Kecil = duluan.'
                AFTER condition_json");
        }

        // 5. is_default
        if (! Schema::hasColumn('tblflow_transition', 'is_default')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Edge default jika tidak ada condition yg match (per from_node + action_code).'
                AFTER priority_no");
        }

        // 6. is_active
        if (! Schema::hasColumn('tblflow_transition', 'is_active')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1
                COMMENT 'Nonaktif tanpa menghapus.'
                AFTER is_default");
        }

        // 7. transition_config_json
        if (! Schema::hasColumn('tblflow_transition', 'transition_config_json')) {
            DB::statement("ALTER TABLE tblflow_transition
                ADD COLUMN transition_config_json JSON NULL
                AFTER is_active");
        }

        // 8. Unique index transition_code per flow_version
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM tblflow_transition WHERE Key_name = 'uq_tbl_flow_transition_version_code'"
        ))->isNotEmpty();

        if (! $indexExists && Schema::hasColumn('tblflow_transition', 'transition_code')) {
            DB::statement("CREATE UNIQUE INDEX uq_tbl_flow_transition_version_code
                ON tblflow_transition (idtblflow_version, transition_code)");
        }
    }

    public function down(): void
    {
        // Drop index dulu
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM tblflow_transition WHERE Key_name = 'uq_tbl_flow_transition_version_code'"
        ))->isNotEmpty();

        if ($indexExists) {
            DB::statement("DROP INDEX uq_tbl_flow_transition_version_code ON tblflow_transition");
        }

        // Drop kolom (urutan terbalik)
        foreach (['transition_config_json', 'is_active', 'is_default', 'priority_no',
                  'transition_type', 'transition_name', 'transition_code'] as $col) {
            if (Schema::hasColumn('tblflow_transition', $col)) {
                DB::statement("ALTER TABLE tblflow_transition DROP COLUMN {$col}");
            }
        }
    }
};
