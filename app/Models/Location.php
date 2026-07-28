<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master list of locations — maps onto the legacy `loc_master` table.
 *
 * A location BELONGS TO a district (`dist_code`). Phase A: hand-typed `loc_code`,
 * no timestamps, and the relationship is expressed in Eloquent (real DB foreign
 * keys arrive in Phase B).
 */
class Location extends Model
{
    protected $table = 'loc_master';

    protected $primaryKey = 'loc_code';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['loc_code', 'loc_name', 'dist_code'];

    /** The district this location belongs to. */
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
            $q->whereRaw('LOWER(loc_name) LIKE ?', ['%'.strtolower($term).'%'])
                ->orWhereRaw('CAST(loc_code AS TEXT) LIKE ?', ['%'.$term.'%']);
        });
    }
}
