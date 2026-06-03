<?php
// One-off: mundurkan request ke status "Menunggu BMH" untuk keperluan testing.
// Cara kerja:
//  - Hapus task non-BMH (RRM/NRM/dst.) beserta candidates & action_log-nya.
//  - Reset task BMH ke OPEN (kosongkan decision_code/note, completed_*).
//  - Reset instance ke RUNNING + current_step = BMH.
//  - Reset request ke IN_PROGRESS.
//  - Reaktifkan token.

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TblTask;
use App\Models\TblTaskCandidate;
use App\Models\TblProcessInstance;
use App\Models\TblProcessToken;
use App\Models\TblApprovalRequest;
use App\Models\TblActionLog;
use App\Models\TblFlowStep;
use Illuminate\Support\Facades\DB;

$reqNo   = $argv[1] ?? 'RT-V2P6001';
$bmhCode = $argv[2] ?? 'BMH';

$req = TblApprovalRequest::where('source_request_no', $reqNo)->first();
if (! $req) { echo "REQUEST {$reqNo} NOT FOUND\n"; exit(1); }
$inst = TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->first();
if (! $inst) { echo "INSTANCE NOT FOUND\n"; exit(1); }
$bmh = TblFlowStep::where('idtblflow_version', $inst->idtblflow_version)
    ->where('node_code', $bmhCode)->first();
if (! $bmh) { echo "STEP {$bmhCode} not found in version {$inst->idtblflow_version}\n"; exit(1); }

echo "== BEFORE ==\n";
echo "request#{$req->idtblapproval_request} status={$req->request_status}\n";
echo "instance#{$inst->idtblprocess_instance} status={$inst->instance_status} current_step={$inst->idtblflow_step_current}\n";
foreach (TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)->orderBy('idtbltask')->get() as $t) {
    echo "  task#{$t->idtbltask} step={$t->idtblflow_step} status={$t->task_status}\n";
}

DB::transaction(function () use ($req, $inst, $bmh) {
    // Hapus task selain BMH + dependensinya
    $otherIds = TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)
        ->where('idtblflow_step', '!=', $bmh->idtblflow_step)
        ->pluck('idtbltask');
    if ($otherIds->isNotEmpty()) {
        TblTaskCandidate::whereIn('task_id', $otherIds)->delete();
        TblActionLog::whereIn('task_id', $otherIds)->delete();
        TblTask::whereIn('idtbltask', $otherIds)->delete();
    }

    // Reset task BMH ke OPEN
    TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)
        ->where('idtblflow_step', $bmh->idtblflow_step)
        ->update([
            'task_status'            => 'OPEN',
            'decision_code'          => null,
            'decision_note'          => null,
            'idtbluser_completed_by' => null,
            'completed_at'           => null,
        ]);

    // Bersihkan action_log untuk task BMH
    $bmhTaskIds = TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)
        ->where('idtblflow_step', $bmh->idtblflow_step)
        ->pluck('idtbltask');
    TblActionLog::whereIn('task_id', $bmhTaskIds)->delete();

    // Reset instance ke RUNNING + posisi di BMH
    $inst->instance_status        = 'RUNNING';
    $inst->idtblflow_step_current = $bmh->idtblflow_step;
    $inst->ended_at               = null;
    $inst->save();

    // Reset request ke IN_PROGRESS
    $req->request_status          = 'IN_PROGRESS';
    $req->completed_at            = null;
    $req->idtblflow_step_current  = $bmh->idtblflow_step;
    $req->save();

    // Aktifkan kembali token
    TblProcessToken::where('idtblprocess_instance', $inst->idtblprocess_instance)
        ->update(['token_status' => 'ACTIVE', 'completed_at' => null]);
});

echo "\n== AFTER ==\n";
$inst = $inst->fresh();
$req  = $req->fresh();
echo "request status={$req->request_status}\n";
echo "instance status={$inst->instance_status} current_step={$inst->idtblflow_step_current}\n";
foreach (TblTask::where('idtblprocess_instance', $inst->idtblprocess_instance)->orderBy('idtbltask')->get() as $t) {
    echo "  task#{$t->idtbltask} step={$t->idtblflow_step} status={$t->task_status} assigned={$t->idtbluser_assigned}\n";
}
echo "\nOK. Sekarang login sebagai BMH (11110247) dan approve via UI untuk test full flow.\n";
