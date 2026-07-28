<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master list of treasuries — maps onto our own `treasury_master` table.
 *
 * A treasury BELONGS TO a district (`dist_code`). Same shape as Location, but this is a
 * table we designed ourselves, so the district link is a real DB foreign key (see the
 * migration), not just an Eloquent relationship.
 *
 *  - `treasury_code` is a hand-typed digit-string like "01" → NOT auto-incrementing, and
 *    keyType 'string' so Eloquent never coerces "01" to the integer 1.
 *  - Reference data, so no created_at / updated_at columns (like the other masters).
 */
class Treasury extends Model
{
    protected $table = 'treasury_master';

    protected $primaryKey = 'treasury_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['treasury_code', 'dist_code', 'treasury_name'];

    /** The district this treasury belongs to. */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'dist_code', 'dist_code');
    }

    /** Case-insensitive search over code + name (works on Postgres and SQLite). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            // treasury_code is already a varchar, so a plain LIKE is enough.
            $q->whereRaw('LOWER(treasury_name) LIKE ?', ['%'.strtolower($term).'%'])
                ->orWhere('treasury_code', 'like', '%'.$term.'%');
        });
    }
}
