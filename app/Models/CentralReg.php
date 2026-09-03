<?php

namespace App\Models;

use App\Models\Scopes\OwnedByUserScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Central Register row = the official booking of one first-receipt into the CR ledger,
 * carrying the CR receipt number stamped during Entry CR. Maps onto `central_reg`.
 *
 * `sl_no` comes from the counter (counter_centralreg), not an auto-increment. This model is
 * intentionally NOT ownership-scoped: it is resolved as a relation off first_receipt, whose
 * own scope already decides who may see the parent row.
 */
class CentralReg extends Model
{
    protected $table = 'central_reg';
    protected $primaryKey = 'sl_no';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    /** The first receipt this CR row was generated from. */
    public function firstReceipt(): BelongsTo
    {
        // Lift the parent's ownership scope: a resolved relation must not depend on who is logged in.
        return $this->belongsTo(FirstReceipt::class, 'first_receipt_sl_no', 'sl_no')
            ->withoutGlobalScope(OwnedByUserScope::class);
    }
}
