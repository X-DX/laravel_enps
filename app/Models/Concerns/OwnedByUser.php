<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByUserScope;
use Illuminate\Support\Facades\Auth;

/**
 * Give a model row-level ownership. Adding `use OwnedByUser;` to a model:
 *   1. applies OwnedByUserScope to every query (per-user visibility; admins see all), and
 *   2. stamps the owner column with the current user id when a new row is created.
 *
 * Both target tables (first_receipt, allotment_accnt_no) store the owner in `user_id`; a model
 * with a different column can override ownerColumn().
 */
trait OwnedByUser
{
    public static function bootOwnedByUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope());

        static::creating(function ($model): void {
            $column = $model->ownerColumn();

            if (Auth::check() && empty($model->getAttribute($column))) {
                $model->setAttribute($column, Auth::id());
            }
        });
    }

    /** The column holding the owning user id. Override in a model if it differs. */
    public function ownerColumn(): string
    {
        return 'user_id';
    }
}
