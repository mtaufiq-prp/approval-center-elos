<?php

namespace App\Jobs;

use App\Models\TblSlaEscalationLog;
use App\Models\TblTask;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SlaEscalationJob
 *
 * Dijadwalkan setiap 30 menit (lihat routes/console.php).
 *
 * Tugasnya:
 *  1. Cari semua task OPEN yang sudah melewati due_at.
 *  2. Jika belum ada SLA log untuk task + level ini, buat SLA log.
 *  3. Log peringatan ke sistem (notifikasi akan ditambahkan di fase
 *     NotificationService nanti).
 *
 * Fase awal: satu level eskalasi. Level lebih tinggi bisa dikonfigurasi
 * di sla_escalation_log.escalation_level untuk laporan eskalasi berulang.
 */
class SlaEscalationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function handle(): void
    {
        $overdue = TblTask::with(['approvalRequest', 'flowStep', 'candidates.user'])
            ->where('task_status', 'OPEN')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        if ($overdue->isEmpty()) {
            return;
        }

        Log::info("SlaEscalationJob: {$overdue->count()} task overdue ditemukan.");

        foreach ($overdue as $task) {
            $this->processTask($task);
        }
    }

    private function processTask(TblTask $task): void
    {
        try {
            // Hitung berapa jam sudah lewat
            $hoursOverdue = (int) now()->diffInHours($task->due_at);
            $level        = max(1, (int) ceil($hoursOverdue / 24)); // Level naik tiap 24 jam

            // Cek apakah sudah ada SLA log level ini untuk task ini
            $alreadyEscalated = TblSlaEscalationLog::where('task_id', $task->idtbltask)
                ->where('escalation_level', $level)
                ->exists();

            if ($alreadyEscalated) {
                return;
            }

            // Buat SLA log
            $reqNo   = $task->approvalRequest?->source_request_no ?? "#{$task->idtblapproval_request}";
            $stepName = $task->flowStep?->step_name ?? "Step #{$task->idtblflow_step}";

            TblSlaEscalationLog::create([
                'task_id'               => $task->idtbltask,
                'idtblapproval_request' => $task->idtblapproval_request,
                'escalation_level'      => $level,
                'escalation_message'    => "Task OVERDUE {$hoursOverdue} jam. Request: {$reqNo} | Step: {$stepName}.",
                'status'                => 'TRIGGERED',
            ]);

            // Kirim notifikasi eskalasi ke approver/kandidat aktif (#89)
            $notifier  = app(NotificationService::class);
            $recipients = $task->candidates
                ->where('is_active', 1)
                ->map(fn($c) => $c->user)
                ->filter();
            if ($recipients->isEmpty() && $task->assignedUser) {
                $recipients = collect([$task->assignedUser]);
            }
            foreach ($recipients as $user) {
                $notifier->notifyEscalation($task, $user, $level, $hoursOverdue);
            }

            Log::warning(
                "SLA Escalation L{$level}: task #{$task->idtbltask} | req {$reqNo} | " .
                "step {$stepName} | overdue {$hoursOverdue} jam | notifikasi: {$recipients->count()}"
            );

        } catch (\Throwable $e) {
            Log::error("SlaEscalationJob error task #{$task->idtbltask}: " . $e->getMessage());
        }
    }
}
