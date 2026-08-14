<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * One closure record per closed account (table `account_closure`, PK = account_no).
 * Owns the closure detail; the account's open/closed status lives in allotment_accnt_no.isactive.
 */
class AccountClosure extends Model
{
    protected $table = 'account_closure';
    protected $primaryKey = 'account_no';
    public $incrementing = false;      // the PK is a string, not an auto number
    protected $keyType = 'string';
    public const UPDATED_AT = null;    // only created_at exists (no updated_at column)

    protected $fillable = [
        'account_no',
        'closure_reason_id',
        'closing_date',
        'deduction_month',
        'deduction_year',
        'closed_by',
    ];

    protected $casts = [
        'closing_date' => 'date',
    ];

    /** The reason chosen at closure (closure_reason_id → m_closure_reason.id). */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(ClosureReason::class, 'closure_reason_id', 'id');
    }

    /** The account this closure belongs to (account_no → allotment_accnt_no.account_no). */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'account_no', 'account_no');
    }

    /** Search the closed register by account number or the holder's name. */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', function ($q) use ($search) {
            $term = '%' . strtolower($search) . '%';
            $q->where(function ($w) use ($term) {
                $w->whereRaw('LOWER(account_no) LIKE ?', [$term])
                    ->orWhereHas('subscriber', fn($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        });
    }
}
