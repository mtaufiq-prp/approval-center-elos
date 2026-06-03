<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit event — perubahan master data atau event sistem penting.
 *
 * Tabel : tblaudit_event
 * PK    : idtblaudit_event
 *
 * Append-only: hanya created_at.
 *
 * Pola pemakaian:
 *   entity_type = 'tblflow_definition' / 'tblrouting_rule' / dll.
 *   entity_id   = PK record yang diubah
 *   event_code  = MASTER_CREATED / MASTER_UPDATED / MASTER_DELETED /
 *                 FLOW_DEPLOYED / ROUTING_RULE_NOT_FOUND / SECRET_ROTATED /
 *                 dll.
 *   old_value_json / new_value_json: snapshot before/after.
 *
 * JANGAN menyimpan field rahasia (password, client_secret) di kolom
 * old_value_json / new_value_json. AuditTrailService bertanggung jawab
 * meredact field sensitif.
 */
class TblAuditEvent extends Model
{
    protected $table      = 'tblaudit_event';
    protected $primaryKey = 'idtblaudit_event';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'event_code',
        'event_message',
        'old_value_json',
        'new_value_json',
        'idtbluser_actor',
        'actor_ref',
        'client_ip',
    ];

    protected $casts = [
        'entity_id'        => 'integer',
        'old_value_json'   => 'array',
        'new_value_json'   => 'array',
        'idtbluser_actor'  => 'integer',
        'created_at'       => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_actor', 'idtbluser');
    }
}
