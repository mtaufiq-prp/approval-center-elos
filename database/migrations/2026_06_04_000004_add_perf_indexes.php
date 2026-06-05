<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #13 (review iterasi lanjutan): index untuk hot read-path monitoring.
 *
 * Listing default Monitoring melakukan `ORDER BY created_at DESC LIMIT 20` tanpa
 * filter → tidak ada index yang bisa memenuhi sort → full scan + filesort yang
 * memburuk linear seiring volume (≈1.44M baris/hari pada 1000 req/menit).
 *
 * idx_tbl_request_status (request_status, submitted_at) yang ada TIDAK membantu
 * karena sort memakai created_at, bukan submitted_at. Kita tambah:
 *   - idx_tbl_request_created          (created_at)                 → default view
 *   - idx_tbl_request_status_created   (request_status, created_at) → filter status + sort
 *
 * Tabel hot-path lain sudah ber-index memadai (uq_tbl_request_source_doc,
 * uq_tbl_request_idempotency, idx_tbl_task_inbox_user, idx_tbl_routing_rule_lookup,
 * idx_tbl_callback_status, idx_tbl_notif_status) — tidak ditambah index sembarangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('tblapproval_request', 'idx_tbl_request_created')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->index('created_at', 'idx_tbl_request_created');
            });
        }

        if (! $this->indexExists('tblapproval_request', 'idx_tbl_request_status_created')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->index(['request_status', 'created_at'], 'idx_tbl_request_status_created');
            });
        }
    }

    public function down(): void
    {
        foreach (['idx_tbl_request_created', 'idx_tbl_request_status_created'] as $idx) {
            if ($this->indexExists('tblapproval_request', $idx)) {
                Schema::table('tblapproval_request', function ($table) use ($idx) {
                    $table->dropIndex($idx);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return ! empty($rows);
    }
};
