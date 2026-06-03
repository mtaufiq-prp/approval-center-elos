<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('tbldocument_type', 'sample_context_json')) {
            DB::statement("
                ALTER TABLE tbldocument_type
                ADD COLUMN sample_context_json JSON NULL
                COMMENT 'Contoh payload context_json dari source app — dipakai untuk drag-drop schema builder'
                AFTER form_schema
            ");
        }
    }
    public function down(): void {
        if (Schema::hasColumn('tbldocument_type', 'sample_context_json')) {
            DB::statement("ALTER TABLE tbldocument_type DROP COLUMN sample_context_json");
        }
    }
};
