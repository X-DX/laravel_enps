<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Interest rate declared for each financial year — maps onto the legacy `rate` table.
 *
 * `id` is auto-generated (its sequence is in sync with the data); `rate` is stored as
 * a double (e.g. 8.8). Phase A: no timestamps.
 */
class InterestRate extends Model
{
    protected $table = 'rate';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['fin_year', 'rate'];

    protected function casts(): array
    {
        return ['rate' => 'float'];
    }
}
