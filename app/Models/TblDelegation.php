<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delegasi approval — substitusi approver pada periode tertentu.
 *
 * Tabel : tbldelegation
 * PK    : idtbldelegation
 *
 * Scope opsional per source_app dan/atau document_type. Jika keduanya
 * NULL, delegasi berlaku global untuk semua approval delegator.
 *
 * Dipakai oleh AssigneeResolverService untuk substitusi otomatis kandidat
 * approver saat delegator tidak aktif.
 */
class TblDelegation extends Model
{
    protected $table      = 'tbldelegation';
    protected $primaryKey = 'idtbldelegation';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtbluser_delegator',
        'idtbluser_delegate',
        'idtblsource_app',
        'idtbldocument_type',
        'start_at',
        'end_at',
        'reason',
        'is_active',
        'idtbluser_created_by',
    ];

    protected $casts = [
        'idtbluser_delegator'  => 'integer',
        'idtbluser_delegate'   => 'integer',
        'idtblsource_app'      => 'integer',
        'idtbldocument_type'   => 'integer',
        'idtbluser_created_by' => 'integer',
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'is_active'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    /**
     * Delegasi yang aktif pada waktu tertentu (default: sekarang).
     */
    public function scopeActiveAt(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query->where('is_active', 1)
                     ->where('start_at', '<=', $at)
                     ->where('end_at', '>=', $at);
    }

    public function scopeForDelegator(Builder $query, int $idtbluser): Builder
    {
        return $query->where('idtbluser_delegator', $idtbluser);
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_delegator', 'idtbluser');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_delegate', 'idtbluser');
    }

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(TblDocumentType::class, 'idtbldocument_type', 'idtbldocument_type');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_created_by', 'idtbluser');
    }
}
