<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Approval Center.
 *
 * Tabel : tbluser
 * PK    : idtbluser
 *
 * Extends Authenticatable agar bisa dipakai Laravel Auth.
 *
 * Catatan auth:
 * - Login menggunakan `user_ref` ATAU `email` (lihat LoginController).
 * - Password disimpan di kolom `password` (bcrypt) — kolom hasil
 *   migration ALTER tbluser.
 * - Field auth tambahan: must_change_password, last_login_at,
 *   password_changed_at, remember_token.
 *
 * Catatan referensi superior:
 * - idtbluser_superior dipakai oleh AssigneeResolver tipe SUPERIOR.
 * - Self-referencing nullable (top-level user tidak punya atasan).
 */
class TblUser extends Authenticatable
{
    use Notifiable;

    protected $table      = 'tbluser';
    protected $primaryKey = 'idtbluser';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'user_ref',
        'full_name',
        'email',
        'phone',
        'idtblorg_unit',
        'idtblposition',
        'idtbluser_superior',
        'is_active',
        // field auth (ditambahkan via migration ALTER)
        'password',
        'must_change_password',
        'last_login_at',
        'password_changed_at',
    ];

    /**
     * Field rahasia — selalu di-hidden dari serialisasi & response.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'idtblorg_unit'        => 'integer',
        'idtblposition'        => 'integer',
        'idtbluser_superior'   => 'integer',
        'is_active'            => 'boolean',
        'must_change_password' => 'boolean',
        'last_login_at'        => 'datetime',
        'password_changed_at'  => 'datetime',
        'password'             => 'hashed', // Laravel 10+ auto-hash on set
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // AUTHENTICATABLE OVERRIDES
    // ---------------------------------------------------------------------

    /**
     * Field yang menyimpan password. Wajib karena nama kolom kita
     * adalah 'password' (sama dengan default Laravel) tapi kita
     * eksplisitkan untuk kejelasan.
     */
    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    /**
     * Kolom yang dipakai untuk auth identifier (PK).
     * Default Authenticatable membaca $this->primaryKey, jadi sudah
     * otomatis idtbluser. Override eksplisit untuk dokumentasi.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }

    // ---------------------------------------------------------------------
    // ROLE HELPERS (dipakai EnsureUserHasRole middleware)
    // ---------------------------------------------------------------------

    /**
     * Cek apakah user punya minimal salah satu role_code yang diberikan.
     *
     * @param array<int,string>|string $roleCodes
     */
    public function hasAnyRole(array|string $roleCodes): bool
    {
        $codes = is_array($roleCodes) ? $roleCodes : [$roleCodes];
        if (empty($codes)) {
            return false;
        }

        return $this->roles()
            ->whereIn('role_code', $codes)
            ->where('tblrole.is_active', 1)
            ->exists();
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(TblOrgUnit::class, 'idtblorg_unit', 'idtblorg_unit');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(TblPosition::class, 'idtblposition', 'idtblposition');
    }

    /**
     * Atasan langsung (self-ref).
     */
    public function superior(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_superior', 'idtbluser');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(TblUser::class, 'idtbluser_superior', 'idtbluser');
    }

    /**
     * Roles (many-to-many via tbluser_role).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            TblRole::class,
            'tbluser_role',     // pivot table
            'idtbluser',        // FK pivot ke user
            'idtblrole',        // FK pivot ke role
            'idtbluser',        // PK user
            'idtblrole'         // PK role
        );
    }

    /**
     * Membership approval group (many-to-many via tblapproval_group_member).
     */
    public function approvalGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            TblApprovalGroup::class,
            'tblapproval_group_member',
            'idtbluser',
            'idtblapproval_group',
            'idtbluser',
            'idtblapproval_group'
        )->wherePivot('is_active', 1);
    }

    /**
     * Delegasi YANG DIBERIKAN user ini (sebagai delegator).
     */
    public function delegationsAsDelegator(): HasMany
    {
        return $this->hasMany(TblDelegation::class, 'idtbluser_delegator', 'idtbluser');
    }

    /**
     * Delegasi YANG DITERIMA user ini (sebagai delegate).
     */
    public function delegationsAsDelegate(): HasMany
    {
        return $this->hasMany(TblDelegation::class, 'idtbluser_delegate', 'idtbluser');
    }

    /**
     * Task yang di-assign langsung ke user ini.
     */
    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(TblTask::class, 'idtbluser_assigned', 'idtbluser');
    }
}
