<?php

namespace App\Models;

use App\Models\Scopes\OwnedByUserScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PRAN (Permanent Retirement Account Number) — maps onto the legacy `pran_no` table.
 * Linked to a subscriber by the account number. (which is UNIQUE here → one PRAN per account).
 */
class PranNo extends Model
{
    protected $table = 'pran_no';
    protected $primaryKey = 'account_no';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'account_no',
        'pran_no',
        'nira_account',
        'pran_allotment_date',
        'ddo_reg',
        'save_flag',
        'entry_date',
        'finalize_date',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'pran_allotment_date' => 'date',
        'entry_date' => 'date',
        'finalize_date' => 'date',
    ];

    /**
     * The account this PRAN belongs to (account_no → allotment_accnt_no.account_no).
     *
     * The ownership scope is lifted on purpose: this is a foreign-key LOOKUP, not a list of
     * someone's records. A belongsTo that returns null because of who is logged in is a bug,
     * not a security feature — the PRAN row itself already decided what may be shown.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'account_no', 'account_no')
            ->withoutGlobalScope(OwnedByUserScope::class);
    }
}
