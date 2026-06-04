<?php

namespace App\Services;

use App\Models\TblAuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * AuditTrailService
 *
 * Wrapper sederhana untuk insert ke tblaudit_event.
 *
 * Prinsip:
 *  - Semua create/update/activate/deactivate/deploy/clone/reset password/
 *    rotate secret pada master data WAJIB lewat service ini.
 *  - Service ini hanya mencatat audit; tidak melakukan transaksi.
 *  - Pemanggil bertanggung jawab memastikan field rahasia di-redact
 *    sebelum dikirim ke recordChange().
 */
class AuditTrailService
{
    /**
     * Field yang otomatis di-redact dari snapshot before/after.
     * Bisa ditambah lewat parameter $extraRedactFields.
     */
    private const ALWAYS_REDACT = [
        'password',
        'remember_token',
        'client_secret_hash',
        'client_secret',
        'secret',
        'plain_secret',
        'token',
    ];

    /**
     * Catat perubahan master data.
     *
     * @param string                $entityType  Nama tabel target, mis. 'tblsource_app'
     * @param int|string|null       $entityId    PK target (null untuk DELETE setelah row hilang)
     * @param string                $eventCode   Mis. MASTER_CREATED, MASTER_UPDATED, MASTER_DEACTIVATED,
     *                                            ROLE_ASSIGNED, ROLE_REMOVED, GROUP_MEMBER_ADDED,
     *                                            GROUP_MEMBER_REMOVED, USER_PASSWORD_RESET,
     *                                            API_SECRET_ROTATED, API_CLIENT_REVOKED,
     *                                            FLOW_DEPLOYED, FLOW_VALIDATED, FLOW_CLONED
     * @param array|null            $oldValues   Snapshot sebelum (null untuk CREATE)
     * @param array|null            $newValues   Snapshot sesudah (null untuk DELETE)
     * @param string|null           $message     Pesan deskriptif singkat
     * @param array                 $extraRedact Field tambahan untuk di-redact
     */
    public function recordChange(
        string $entityType,
        int|string|null $entityId,
        string $eventCode,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $message = null,
        array $extraRedact = []
    ): TblAuditEvent {
        $redactFields = array_unique(array_merge(self::ALWAYS_REDACT, $extraRedact));

        $actor    = Auth::user();
        $actorRef = $actor->user_ref ?? 'SYSTEM';

        return TblAuditEvent::create([
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'event_code'      => $eventCode,
            'event_message'   => $message ? mb_substr($message, 0, 500) : null,
            'old_value_json'  => $oldValues !== null ? $this->redact($oldValues, $redactFields) : null,
            'new_value_json'  => $newValues !== null ? $this->redact($newValues, $redactFields) : null,
            'idtbluser_actor' => $actor->idtbluser ?? null,
            'actor_ref'       => $actorRef,
            'client_ip'       => Request::ip(),
        ]);
    }

    /**
     * Helper khusus CREATE.
     */
    public function recordCreated(Model $model, ?string $message = null, array $extraRedact = []): TblAuditEvent
    {
        return $this->recordChange(
            entityType:  $model->getTable(),
            entityId:    $model->getKey(),
            eventCode:   'MASTER_CREATED',
            oldValues:   null,
            newValues:   $model->getAttributes(),
            message:     $message ?? "Created {$model->getTable()} #{$model->getKey()}",
            extraRedact: $extraRedact
        );
    }

    /**
     * Helper khusus UPDATE dengan diff otomatis.
     * Hanya mencatat field yang berubah (clean & ringkas).
     */
    public function recordUpdated(
        Model $model,
        array $originalValues,
        ?string $message = null,
        array $extraRedact = []
    ): ?TblAuditEvent {
        $changes = $model->getChanges();      // hanya yg berubah setelah save()
        if (empty($changes)) return null;     // #36: jangan tulis audit kosong
        $oldDiff = array_intersect_key($originalValues, $changes);

        return $this->recordChange(
            entityType:  $model->getTable(),
            entityId:    $model->getKey(),
            eventCode:   'MASTER_UPDATED',
            oldValues:   $oldDiff ?: null,
            newValues:   $changes ?: null,
            message:     $message ?? "Updated {$model->getTable()} #{$model->getKey()}",
            extraRedact: $extraRedact
        );
    }

    /**
     * Helper khusus DEACTIVATE (soft).
     */
    public function recordDeactivated(Model $model, ?string $message = null): TblAuditEvent
    {
        return $this->recordChange(
            entityType: $model->getTable(),
            entityId:   $model->getKey(),
            eventCode:  'MASTER_DEACTIVATED',
            oldValues:  ['is_active' => 1],
            newValues:  ['is_active' => 0],
            message:    $message ?? "Deactivated {$model->getTable()} #{$model->getKey()}"
        );
    }

    /**
     * Helper khusus ACTIVATE.
     */
    public function recordActivated(Model $model, ?string $message = null): TblAuditEvent
    {
        return $this->recordChange(
            entityType: $model->getTable(),
            entityId:   $model->getKey(),
            eventCode:  'MASTER_ACTIVATED',
            oldValues:  ['is_active' => 0],
            newValues:  ['is_active' => 1],
            message:    $message ?? "Activated {$model->getTable()} #{$model->getKey()}"
        );
    }

    /**
     * Helper untuk event custom (deploy flow, clone, reset password, dst).
     */
    public function recordEvent(
        string $entityType,
        int|string|null $entityId,
        string $eventCode,
        string $message,
        ?array $newValues = null,
        array $extraRedact = []
    ): TblAuditEvent {
        return $this->recordChange(
            entityType:  $entityType,
            entityId:    $entityId,
            eventCode:   $eventCode,
            oldValues:   null,
            newValues:   $newValues,
            message:     $message,
            extraRedact: $extraRedact
        );
    }

    /**
     * Redact field rahasia dari array (recursive 1 level — cukup untuk
     * snapshot Eloquent yang flat).
     */
    private function redact(array $data, array $redactFields): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $redactFields, true)) {
                $data[$key] = '***REDACTED***';
            }
        }
        return $data;
    }
}
