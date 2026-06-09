<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai ORG_HEAD ke ENUM assignee_type di tblstep_assignee_rule.
 *
 * ORG_HEAD: resolve atasan PEMOHON pada tier tertentu (dept_head|div_head|atasan)
 * dengan menelusuri hierarki organisasi di db_master.tbemployeeit dari NPK pemohon
 * (fakta di context_json). Generik untuk semua source app — approver ditentukan
 * Approval Center, bukan dikirim source app. Menggantikan kebutuhan _computed per-app
 * untuk kasus "atasan organisasi si pemohon". Atasan TETAP (mis. direksi) cukup GROUP.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tblstep_assignee_rule
            MODIFY COLUMN assignee_type
            ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER','JOBTITLE','ORG_HEAD')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tblstep_assignee_rule
            MODIFY COLUMN assignee_type
            ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER','JOBTITLE')
            NOT NULL
        ");
    }
};
