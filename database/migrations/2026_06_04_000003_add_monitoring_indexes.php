<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #73 Index pendukung monitoring: filter idtblsource_app + sort created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('tblapproval_request', 'idx_tbl_request_app_created')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->index(['idtblsource_app', 'created_at'], 'idx_tbl_request_app_created');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('tblapproval_request', 'idx_tbl_request_app_created')) {
            Schema::table('tblapproval_request', function ($table) {
                $table->dropIndex('idx_tbl_request_app_created');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return ! empty($rows);
    }
};
