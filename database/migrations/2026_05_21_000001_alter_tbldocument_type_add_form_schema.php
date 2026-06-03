<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom form_schema ke tbldocument_type.
 *
 * form_schema adalah JSON array yang mendeskripsikan bagaimana
 * context_json ditampilkan di halaman detail approval.
 *
 * Format tiap field:
 * {
 *   "field"   : "nama_field_di_context_json",
 *   "label"   : "Label yang tampil ke user",
 *   "type"    : "text|currency|number|date|datetime|badge|textarea|image|table|list|separator",
 *   "width"   : "full|half|third"   (default: half)
 *   "prefix"  : "Rp "              (untuk currency)
 *   "columns" : ["col1","col2"]    (untuk type: table)
 *   "col_labels": ["Kolom 1","Kolom 2"] (label kolom tabel)
 *   "colors"  : {"RUSAK":"danger","BAIK":"success"}  (untuk type: badge)
 *   "default" : "-"                (nilai jika field kosong)
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbldocument_type', 'form_schema')) {
            DB::statement("
                ALTER TABLE tbldocument_type
                ADD COLUMN form_schema JSON NULL
                COMMENT 'Schema tampilan form context_json untuk approval detail view'
                AFTER description
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbldocument_type', 'form_schema')) {
            DB::statement("ALTER TABLE tbldocument_type DROP COLUMN form_schema");
        }
    }
};
