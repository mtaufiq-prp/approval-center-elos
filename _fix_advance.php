<?php
// One-off: paksa engine memajukan task yang stuck akibat bug enterNode-tidak-buat-task.
// Cara kerja: reset task BMH ke OPEN, reset instance ke RUNNING, lalu panggil
// completeTask via engine. Karena engine sudah di-fix, RRM task akan terbuat.

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TblTask;
use App\Models\TblProcessInstance;
use App\Models\TblProcessToken;
use App\Models\TblUser; 
use App\Models\TblActionLog;
use App\Services\FlowEngineService;
use Illuminate\Support\Facades\DB;

$taskId    = (int) ($argv[1] ?? 14);
$actorRef  = (string) ($argv[2] ?? '11110247'); // BMH approver default

$t = TblTask::find($taskId);
if (! $t) { echo "TASK {$taskId} NOT FOUND\n"; exit(1); }
echo "== BEFORE ==\n";
echo "task#{$t->idtbltask} step={$t->idtblflow_step} status={$t->task_status} completedBy={$t->idtbluser_completed_by}\n";

$inst = TblProcessInstance::find($t->idtblprocess_instance);
echo "instance#{$inst->idtblprocess_instance} status={$inst->instance_status} current_step={$inst->idtblflow_step_current}\n";

DB::transaction(function () use ($t, $inst) {
    // 1) Reset task BMH ke OPEN
    $t->task_status            = 'OPEN';
    $t->decision_code          = null;
    $t->decision_note          = null;
    $t->idtbluser_completed_by = null;
    $t->completed_at           = null;
    $t->save();

    // 2) Reset instance ke RUNNING (mungkin sebelumnya COMPLETED akibat bug)
    $inst->instance_status = 'RUNNING';
    $inst->ended_at        = null;
    $inst->save();

    // 3) Aktifkan kembali token utama
    TblProcessToken::where('idtblprocess_instance', $inst->idtblprocess_instance)
        ->update(['token_status' => 'ACTIVE', 'completed_at' => null]);

    // 4) Hapus action_log lama untuk task ini agar tidak duplikat
    TblActionLog::where('task_id', $t->idtbltask)->delete();
});
echo "Reset OK.\n";

$actor = TblUser::where('user_ref', $actorRef)->first();
if (! $actor) { echo "ACTOR {$actorRef} NOT FOUND\n"; exit(1); }
echo "Actor: {$actor->user_ref} {$actor->full_name}\n";

app(FlowEngineService::class)->completeTask(
    task:         $t->fresh(),
    decisionCode: 'APPROVE',
    decisionNote: '[REPLAY] Advance setelah perbaikan engine',
    actor:        $actor,
);

echo "\n== AFTER ==\n";
$inst = $inst->fresh();
echo "instance status={$inst->instance_status} current_step={$inst->idtblflow_step_current}\n";

$tasks = TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)
    ->orderBy('idtbltask')->get();
foreach ($tasks as $tx) {
    echo "  task#{$tx->idtbltask} step={$tx->idtblflow_step} status={$tx->task_status} "
        . "assigned=" . ($tx->idtbluser_assigned ?? '-')
        . " completedBy=" . ($tx->idtbluser_completed_by ?? '-') . "\n";
}
