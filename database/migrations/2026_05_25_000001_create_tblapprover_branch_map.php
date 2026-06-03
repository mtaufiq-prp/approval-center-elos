<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel mapping: idtblbranch → BMH user_ref + RRM user_ref
 *
 * Dipakai oleh PayloadEnrichmentService untuk inject
 * _computed.bmh_user_ref dan _computed.rrm_user_ref
 * ke context_json sebelum flow engine berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tblapprover_branch_map')) {
            DB::statement("
                CREATE TABLE tblapprover_branch_map (
                    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    idtblbranch     VARCHAR(10)  NOT NULL COMMENT 'Kode cabang SFA',
                    branch_name     VARCHAR(100) NULL,
                    bmh_user_ref    VARCHAR(20)  NOT NULL COMMENT 'NPK BMH (idtblemployee SFA)',
                    rrm_user_ref    VARCHAR(20)  NULL COMMENT 'NPK RRM atasan BMH',
                    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
                    created_at      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                    updated_at      DATETIME(3)  NULL ON UPDATE CURRENT_TIMESTAMP(3),
                    PRIMARY KEY (id),
                    INDEX idx_branch (idtblbranch),
                    INDEX idx_bmh    (bmh_user_ref),
                    INDEX idx_rrm    (rrm_user_ref)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Mapping idtblbranch SFA ke NPK BMH dan RRM untuk approval routing'
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tblapprover_branch_map');
    }
};
