<?php

namespace App\Services;

use App\Models\TblNotificationQueue;
use App\Models\TblTask;
use App\Models\TblUser;
use Illuminate\Support\Collection;

/**
 * NotificationService — producer antrian notifikasi (tblnotification_queue).
 *
 * Menyisipkan baris notifikasi IN_APP (default) yang dikonsumsi oleh
 * ProcessNotificationQueueJob. Channel lain (EMAIL/WA) menyusul; producer ini
 * yang sebelumnya hilang sehingga antrian tidak pernah terisi (#88).
 */
class NotificationService
{
    /**
     * Notifikasi "ada task approval baru" untuk user terkait sebuah task.
     */
    public function notifyTaskAssigned(TblTask $task, TblUser $user): void
    {
        $reqNo = $task->approvalRequest?->source_request_no
            ?? $task->approvalRequest?->source_request_id
            ?? "#{$task->idtblapproval_request}";

        $this->enqueue(
            $task,
            $user,
            subject: "Approval baru menunggu: {$reqNo}",
            message: "Anda memiliki task approval baru ({$reqNo}). Silakan buka inbox untuk memprosesnya.",
        );
    }

    /**
     * Notifikasi eskalasi SLA untuk user terkait (approver / atasan).
     */
    public function notifyEscalation(TblTask $task, TblUser $user, int $level, int $hoursOverdue): void
    {
        $reqNo = $task->approvalRequest?->source_request_no
            ?? $task->approvalRequest?->source_request_id
            ?? "#{$task->idtblapproval_request}";

        $this->enqueue(
            $task,
            $user,
            subject: "Eskalasi SLA L{$level}: {$reqNo} overdue {$hoursOverdue} jam",
            message: "Task approval {$reqNo} telah melewati SLA ({$hoursOverdue} jam). Mohon segera ditindaklanjuti.",
        );
    }

    /**
     * @param Collection<int,TblUser>|iterable<TblUser> $users
     */
    public function notifyTaskAssignedMany(TblTask $task, iterable $users): void
    {
        foreach ($users as $user) {
            $this->notifyTaskAssigned($task, $user);
        }
    }

    private function enqueue(TblTask $task, TblUser $user, string $subject, string $message): void
    {
        // Hindari duplikat IN_APP untuk task+user+subjek yang masih PENDING
        $exists = TblNotificationQueue::where('task_id', $task->idtbltask)
            ->where('idtbluser', $user->idtbluser)
            ->where('subject', $subject)
            ->where('status', 'PENDING')
            ->exists();
        if ($exists) {
            return;
        }

        TblNotificationQueue::create([
            'idtblapproval_request' => $task->idtblapproval_request,
            'task_id'               => $task->idtbltask,
            'idtbluser'             => $user->idtbluser,
            'channel'               => 'IN_APP',
            'recipient'             => (string) ($user->user_ref ?? $user->idtbluser),
            'subject'               => $subject,
            'message'               => $message,
            'status'                => 'PENDING',
            'retry_count'           => 0,
            'next_retry_at'         => now(),
        ]);
    }
}
