<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah nilai JOBTITLE ke ENUM assignee_type di tblstep_assignee_rule.
 *
 * JOBTITLE: lookup approver berdasarkan jobtitleid dari db_master.tbemployeeit.
 * Berguna untuk jabatan yang orangnya bisa berganti (CEO, Packaging Head, dll)
 * sehingga tidak perlu hardcode NPK di konfigurasi flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tblstep_assignee_rule
            MODIFY COLUMN assignee_type
            ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER','JOBTITLE')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tblstep_assignee_rule
            MODIFY COLUMN assignee_type
            ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER')
            NOT NULL
        ");
    }
};
