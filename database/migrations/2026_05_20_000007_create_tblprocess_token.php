<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BPMN-lite: tabel baru tblprocess_token.
 *
 * Persiapan untuk INCLUSIVE/PARALLEL branch di masa depan.
 * Fase awal: engine cukup satu token utama per process instance.
 *
 * token_status:
 *  ACTIVE, WAITING, COMPLETED, CANCELLED, ERROR
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tblprocess_token')) {
            return;
        }

        Schema::create('tblprocess_token', function (Blueprint $t) {
            $t->bigIncrements('idtblprocess_token');

            $t->unsignedBigInteger('idtblprocess_instance');
            $t->unsignedBigInteger('idtblapproval_request');
            $t->unsignedBigInteger('idtblflow_step_current')->nullable();
            $t->unsignedBigInteger('idtblprocess_token_parent')->nullable()
              ->comment('Parent token jika branch (parallel/inclusive).');

            $t->string('token_key', 100)->nullable()
              ->comment('Identifier token (mis. hash/uuid pendek).');
            $t->string('token_status', 30)->default('ACTIVE')
              ->comment('ACTIVE, WAITING, COMPLETED, CANCELLED, ERROR');
            $t->string('branch_key', 100)->nullable()
              ->comment('Label cabang untuk debug, mis. branch:KEMASAN_BOCOR');

            $t->dateTime('created_at', 3)->useCurrent();
            $t->dateTime('completed_at', 3)->nullable();
            $t->dateTime('cancelled_at', 3)->nullable();

            $t->index('idtblprocess_instance', 'idx_tbl_token_instance');
            $t->index('idtblapproval_request', 'idx_tbl_token_request');
            $t->index('token_status',          'idx_tbl_token_status');
            $t->index('idtblprocess_token_parent', 'idx_tbl_token_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblprocess_token');
    }
};
