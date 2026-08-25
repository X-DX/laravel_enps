<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level ownership: every query on a model using this scope is silently narrowed to the
 * rows created by the authenticated user — so one operator never sees another's records.
 *
 * Two deliberate exceptions:
 *   - No authenticated user (console, seeders, queued jobs): no filter, so tooling still works.
 *   - Admins (User::isAdmin, legacy role_flag 'A'): no filter, so they see every user's rows.
 *
 * Because it hooks EVERY Eloquent query, it also protects route-model binding: fetching another
 * user's record by id returns null → a 404, never a data leak. To query across users on purpose,
 * call Model::withoutGlobalScope(OwnedByUserScope::class).
 */
class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check() || Auth::user()?->isAdmin()) {
            return;
        }

        // qualifyColumn prefixes the table name, so it stays unambiguous under joins.
        $builder->where($model->qualifyColumn($model->ownerColumn()), Auth::id());
    }
}
