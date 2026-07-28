<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master list of banks/branches — maps onto the legacy `bank_master` table.
 *
 * Phase A: mapped onto the existing table as-is.
 *  - `bank_code` is a hand-assigned code (an admin types it), so NOT auto-incrementing.
 *  - `bank_name` / `branch_name` are varchar(30) in the legacy schema.
 */
class Bank extends Model
{
    protected $table = 'bank_master';

    protected $primaryKey = 'bank_code';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['bank_code', 'bank_name', 'branch_name'];

    /** Case-insensitive search over code + bank + branch (works on Postgres and SQLite). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.strtolower($term).'%';
            $q->whereRaw('LOWER(bank_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(branch_name) LIKE ?', [$like])
                ->orWhereRaw('CAST(bank_code AS TEXT) LIKE ?', ['%'.$term.'%']);
        });
    }
}
