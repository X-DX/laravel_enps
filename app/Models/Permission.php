<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['key', 'name', 'group', 'legacy_menu_id'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Users granted this permission directly (mirrors the legacy per-user model).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_permission',
            'permission_id',  // this model's key on the pivot
            'user_id',        // related model's key on the pivot
            'id',             // Permission primary key
            'user_id',        // User primary key
        );
    }
}
