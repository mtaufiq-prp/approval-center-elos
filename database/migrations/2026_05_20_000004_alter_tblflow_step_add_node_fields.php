<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BPMN-lite: ALTER tblflow_step menjadi Node flowchart.
 *
 * Perubahan:
 *  1. Perlebar ENUM step_type untuk menambahkan 'DECISION'.
 *     Schema asli: ENUM('START','APPROVAL','REVIEW','NOTIFICATION','SYSTEM','END')
 *     Target     : tambah 'DECISION' — nilai lama tetap valid.
 *
 *  2. Tambah field node BPMN-lite:
 *     - node_code        unique per flow_version (composite index)
 *     - gateway_type     ENUM untuk DECISION
 *     - pos_x, pos_y     posisi node di canvas
 *     - node_width, node_height
 *     - node_style_json  warna, icon, border
 *     - node_config_json konfigurasi tambahan per node
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Perlebar ENUM step_type
        //    Aman: ALTER ENUM tambah nilai tidak merusak baris existing.
        DB::statement(
            "ALTER TABLE tblflow_step
             MODIFY COLUMN step_type
             ENUM('START','APPROVAL','REVIEW','NOTIFICATION','SYSTEM','END','DECISION')
             NOT NULL DEFAULT 'APPROVAL'
             COMMENT 'Node type (BPMN-lite). DECISION = percabangan logic, tidak buat task.'"
        );

        // 2) Tambah field node bila belum ada
        Schema::table('tblflow_step', function ($table) {
            if (! Schema::hasColumn('tblflow_step', 'node_code')) {
                $table->string('node_code', 100)
                      ->nullable()
                      ->after('step_name')
                      ->comment('Kode unik node dalam satu flow_version. Contoh: START, BMH, DECISION_KONDISI, END.');
            }
            if (! Schema::hasColumn('tblflow_step', 'gateway_type')) {
                // ENUM ditulis raw karena Schema::enum kadang quirky di MariaDB
                DB::statement(
                    "ALTER TABLE tblflow_step
                     ADD COLUMN gateway_type ENUM('NONE','EXCLUSIVE','INCLUSIVE','PARALLEL')
                     NOT NULL DEFAULT 'NONE'
                     COMMENT 'Strategi gateway untuk DECISION node. Non-DECISION wajib NONE.'
                     AFTER step_type"
                );
            }
            if (! Schema::hasColumn('tblflow_step', 'pos_x')) {
                $table->integer('pos_x')->nullable()->after('gateway_type');
            }
            if (! Schema::hasColumn('tblflow_step', 'pos_y')) {
                $table->integer('pos_y')->nullable()->after('pos_x');
            }
            if (! Schema::hasColumn('tblflow_step', 'node_width')) {
                $table->integer('node_width')->nullable()->after('pos_y');
            }
            if (! Schema::hasColumn('tblflow_step', 'node_height')) {
                $table->integer('node_height')->nullable()->after('node_width');
            }
            if (! Schema::hasColumn('tblflow_step', 'node_style_json')) {
                $table->json('node_style_json')->nullable()->after('node_height');
            }
            if (! Schema::hasColumn('tblflow_step', 'node_config_json')) {
                $table->json('node_config_json')->nullable()->after('node_style_json');
            }
        });

        // 3) Composite unique index (idtblflow_version, node_code)
        //    Hanya jika kolom node_code sudah ada & index belum ada.
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM tblflow_step WHERE Key_name = 'uq_tbl_flow_step_version_node_code'"
        ))->isNotEmpty();

        if (! $indexExists && Schema::hasColumn('tblflow_step', 'node_code')) {
            // NULL-safe: MySQL unique index mengizinkan multiple NULL, jadi
            // node yang belum di-assign node_code tidak akan bentrok.
            DB::statement(
                "CREATE UNIQUE INDEX uq_tbl_flow_step_version_node_code
                 ON tblflow_step (idtblflow_version, node_code)"
            );
        }
    }

    public function down(): void
    {
        // Drop unique index dulu
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM tblflow_step WHERE Key_name = 'uq_tbl_flow_step_version_node_code'"
        ))->isNotEmpty();

        if ($indexExists) {
            DB::statement("DROP INDEX uq_tbl_flow_step_version_node_code ON tblflow_step");
        }

        Schema::table('tblflow_step', function ($table) {
            foreach (['node_config_json', 'node_style_json', 'node_height', 'node_width',
                      'pos_y', 'pos_x', 'gateway_type', 'node_code'] as $col) {
                if (Schema::hasColumn('tblflow_step', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Kembalikan ENUM step_type ke versi asli
        DB::statement(
            "ALTER TABLE tblflow_step
             MODIFY COLUMN step_type
             ENUM('START','APPROVAL','REVIEW','NOTIFICATION','SYSTEM','END')
             NOT NULL DEFAULT 'APPROVAL'"
        );
    }
};
