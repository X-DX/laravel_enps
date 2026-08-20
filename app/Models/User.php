<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use Notifiable;

    /** A password must be changed after this many days (legacy: 30). */
    private const PASSWORD_MAX_AGE_DAYS = 30;

    /** In-request memo of the effective permission keys. */
    protected ?Collection $permissionKeyCache = null;

    /**
     * The legacy production table this model maps onto.
     *
     * We reuse the migrated data as-is, so the model points at `user_account`
     * instead of Laravel's default `users` table (compatibility-first / Phase A).
     */
    protected $table = 'user_account';

    /**
     * The primary key is `user_id`: a varchar(10) assigned by hand, not an
     * auto-increment integer. Both flags below stop Eloquent from treating the
     * key as an incrementing integer (which would cast "admin" to 0).
     */
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The legacy table has no created_at / updated_at columns.
     */
    public $timestamps = false;

    /**
     * Mass-assignable attributes (for later profile/admin screens).
     * `user_id`, `role_flag` and `password` are intentionally excluded so they
     * can only be set explicitly, never via bulk request input.
     */
    protected $fillable = [
        'username',
        'user_mobile',
        'user_email_id',
        'photo',
        'user_status',
        'first_login',
        'last_pwd_change',
    ];

    /**
     * Hidden from array/JSON output.
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Attribute casting.
     *
     * IMPORTANT: `password` is NOT cast to 'hashed'. That cast assumes bcrypt,
     * but the migrated passwords are unsalted SHA-256. Verification and
     * rehash-on-login are handled by a custom hasher (slice 1b).
     */
    protected function casts(): array
    {
        return [
            'user_status'     => 'boolean',
            'first_login'     => 'boolean',
            'last_pwd_change' => 'date',
        ];
    }

    /**
     * The `user_account` table has no `remember_token` column, so we disable
     * the "remember me" feature by reporting an empty token column name.
     */
    public function getRememberTokenName()
    {
        return '';
    }

    /**
     * Whether this user must set a new password before continuing:
     *  - never completed a first login (first_login = 0), or
     *  - no recorded password-change date, or
     *  - the password is older than the maximum age (legacy: 30 days).
     */
    public function mustChangePassword(): bool
    {
        return ! $this->first_login
            || is_null($this->last_pwd_change)
            || $this->last_pwd_change->lt(now()->subDays(self::PASSWORD_MAX_AGE_DAYS));
    }

    /* ----------------------------------------------------------------- */
    /* Authorization (RBAC)                                              */
    /* ----------------------------------------------------------------- */

    /**
     * The user's role, resolved from the legacy `role_flag` char (A/S/U)
     * against roles.code — there is no role_id column on user_account in Phase A.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_flag', 'code');
    }

    /**
     * Permissions granted directly to this user (mirrors the legacy per-user
     * menu_ids model).
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permission',
            'user_id',        // this model's key on the pivot
            'permission_id',  // related model's key on the pivot
            'user_id',        // User primary key
            'id',             // Permission primary key
        );
    }

    /**
     * Admins (legacy role_flag 'A') bypass every permission check.
     */
    public function isAdmin(): bool
    {
        return $this->role_flag === 'A';
    }

    /**
     * Whether the user may perform the given permission key.
     */
    public function hasPermissionTo(string $key): bool
    {
        return $this->isAdmin() || $this->permissionKeys()->contains($key);
    }

    /**
     * Effective permission keys: those granted via the role plus those granted
     * directly. Memoised for the duration of the request.
     */
    public function permissionKeys(): Collection
    {
        if ($this->permissionKeyCache !== null) {
            return $this->permissionKeyCache;
        }

        // Defensive: some pages render where the RBAC tables aren't present (e.g. unrelated
        // auth tests). Treat that as "no permissions" rather than erroring — admins still
        // bypass via isAdmin() before this method is ever reached.
        if (! Schema::hasTable('permissions')) {
            return $this->permissionKeyCache = collect();
        }

        $direct = $this->directPermissions()->pluck('key');

        $viaRole = $this->role
            ? $this->role->permissions->pluck('key')
            : collect();

        return $this->permissionKeyCache = $direct->merge($viaRole)->unique()->values();
    }
}
