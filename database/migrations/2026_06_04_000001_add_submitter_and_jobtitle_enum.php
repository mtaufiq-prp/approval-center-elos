<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #102 Tambah kolom idtblapproval_request.idtbluser_submitter (+ FK) yang
 *      dipakai engine untuk resolusi assignee SUPERIOR/submitter.
 * #103 Selaraskan ENUM tblstep_assignee_rule.assignee_type agar memuat JOBTITLE
 *      (kalau environment di-import dari schema lama tanpa ALTER 2026_05_26).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kolom: hanya tambah bila belum ada (schema kanonik mungkin sudah punya).
        if (! Schema::hasColumn('tblapproval_request', 'idtbluser_submitter')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->unsignedBigInteger('idtbluser_submitter')->nullable()->after('idtblflow_step_current');
            });
        }
        // FK: dijaga terpisah dari kolom. Pada DB hasil import schema, kolom SUDAH ada
        // tapi FK belum → tetap dibuat. Tanpa pemisahan ini, FK tak pernah terpasang.
        if (! $this->foreignKeyExists('tblapproval_request', 'fk_tbl_request_submitter')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->foreign('idtbluser_submitter', 'fk_tbl_request_submitter')
                      ->references('idtbluser')->on('tbluser');
            });
        }

        // Idempoten: pastikan JOBTITLE ada di ENUM assignee_type.
        DB::statement(
            "ALTER TABLE tblstep_assignee_rule MODIFY assignee_type
             ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER','JOBTITLE') NOT NULL"
        );
    }

    public function down(): void
    {
        // Drop FK hanya bila ada → rollback tidak gagal di DB yang FK-nya tak pernah ada.
        if ($this->foreignKeyExists('tblapproval_request', 'fk_tbl_request_submitter')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->dropForeign('fk_tbl_request_submitter');
            });
        }
        if (Schema::hasColumn('tblapproval_request', 'idtbluser_submitter')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->dropColumn('idtbluser_submitter');
            });
        }
    }

    private function foreignKeyExists(string $table, string $fk): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1",
            [$db, $table, $fk]
        );
        return ! empty($rows);
    }
};
