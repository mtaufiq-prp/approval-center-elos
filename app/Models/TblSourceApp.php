<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aplikasi sumber yang mengirim approval ke Approval Center.
 * Contoh: RETUR_BARANG, PR_ONLINE, BSKB, RPD, PIS.
 *
 * Tabel : tblsource_app
 * PK    : idtblsource_app
 */
class TblSourceApp extends Model
{
    protected $table      = 'tblsource_app';
    protected $primaryKey = 'idtblsource_app';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'app_code',
        'app_name',
        'description',
        'base_url',
        'default_callback_url',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function apiClients(): HasMany
    {
        return $this->hasMany(TblApiClient::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function documentTypes(): HasMany
    {
        return $this->hasMany(TblDocumentType::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function flowDefinitions(): HasMany
    {
        return $this->hasMany(TblFlowDefinition::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(TblRoutingRule::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(TblApprovalRequest::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function callbackOutboxes(): HasMany
    {
        return $this->hasMany(TblCallbackOutbox::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(TblIntegrationMessageLog::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(TblDelegation::class, 'idtblsource_app', 'idtblsource_app');
    }
}
