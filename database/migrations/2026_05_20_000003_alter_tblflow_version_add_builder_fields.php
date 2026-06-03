<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BPMN-lite: ALTER tblflow_version menambahkan metadata builder.
 *
 * Field tambahan:
 *  - diagram_json        JSON   metadata visual builder (drag-and-drop fase berikut)
 *  - builder_version     VARCHAR(50)  versi builder yang membuat flow
 *  - validation_status   VARCHAR(30)  DRAFT, VALID, INVALID
 *  - validation_message  TEXT   pesan hasil validasi flow
 *  - validated_at        DATETIME  waktu terakhir flow divalidasi
 *
 * Idempotent: cek hasColumn() sebelum tambah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tblflow_version', function ($table) {
            if (! Schema::hasColumn('tblflow_version', 'diagram_json')) {
                $table->json('diagram_json')
                      ->nullable()
                      ->after('definition_json')
                      ->comment('Metadata visual builder (drag-and-drop) — fase BPMN-lite.');
            }
            if (! Schema::hasColumn('tblflow_version', 'builder_version')) {
                $table->string('builder_version', 50)
                      ->nullable()
                      ->after('diagram_json')
                      ->comment('Versi builder yang membuat flow (mis. v1.0).');
            }
            if (! Schema::hasColumn('tblflow_version', 'validation_status')) {
                $table->string('validation_status', 30)
                      ->nullable()
                      ->after('builder_version')
                      ->comment('DRAFT, VALID, INVALID — diisi oleh FlowValidationService.');
            }
            if (! Schema::hasColumn('tblflow_version', 'validation_message')) {
                $table->text('validation_message')
                      ->nullable()
                      ->after('validation_status')
                      ->comment('Pesan hasil validasi (errors / warnings).');
            }
            if (! Schema::hasColumn('tblflow_version', 'validated_at')) {
                $table->dateTime('validated_at', 3)
                      ->nullable()
                      ->after('validation_message')
                      ->comment('Timestamp validasi terakhir.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tblflow_version', function ($table) {
            foreach (['validated_at', 'validation_message', 'validation_status',
                      'builder_version', 'diagram_json'] as $col) {
                if (Schema::hasColumn('tblflow_version', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
