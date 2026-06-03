<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BPMN-lite: tabel baru tblprocess_route_log.
 *
 * Mencatat jalur node/transition yang dilewati engine pada runtime.
 * Berguna untuk debugging "kenapa dokumen X lewat node Y" dan untuk
 * menjelaskan kepada user kenapa approval melewati / skip step tertentu.
 *
 * Append-only: hanya created_at.
 *
 * route_event contoh:
 *  ENTER_NODE, EXIT_NODE, SKIP_NODE, EVALUATE_TRANSITION,
 *  TRANSITION_MATCH, TRANSITION_NOT_MATCH, TASK_CREATED,
 *  PROCESS_COMPLETED, PROCESS_ERROR
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tblprocess_route_log')) {
            return; // idempotent
        }

        Schema::create('tblprocess_route_log', function (Blueprint $t) {
            $t->bigIncrements('idtblprocess_route_log');

            $t->unsignedBigInteger('idtblapproval_request');
            $t->unsignedBigInteger('idtblprocess_instance');
            $t->unsignedBigInteger('idtblflow_step')->nullable();
            $t->unsignedBigInteger('idtblflow_transition')->nullable();

            $t->string('route_event', 50)
              ->comment('ENTER_NODE, EXIT_NODE, SKIP_NODE, EVALUATE_TRANSITION, TRANSITION_MATCH, TRANSITION_NOT_MATCH, TASK_CREATED, PROCESS_COMPLETED, PROCESS_ERROR');
            $t->string('node_type', 50)->nullable();
            $t->string('action_code', 50)->nullable();
            $t->tinyInteger('condition_result')->nullable()
              ->comment('1 = match, 0 = not match, NULL = N/A');
            $t->json('condition_json')->nullable()
              ->comment('Snapshot condition yang dievaluasi (atau ringkasannya).');
            $t->string('message', 500)->nullable();
            $t->string('created_by', 100)->nullable()
              ->comment('user_ref actor atau SYSTEM untuk auto-step');
            $t->dateTime('created_at', 3)->useCurrent();

            $t->index('idtblapproval_request', 'idx_tbl_route_log_request');
            $t->index('idtblprocess_instance', 'idx_tbl_route_log_instance');
            $t->index('idtblflow_step',        'idx_tbl_route_log_step');
            $t->index('route_event',           'idx_tbl_route_log_event');
            $t->index('created_at',            'idx_tbl_route_log_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblprocess_route_log');
    }
};
