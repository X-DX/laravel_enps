<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A first-register receipt/draft = one incoming money deposit from a DDO (pooled contributions),
 * before it's split into individual contributions later. Maps onto the legacy `first_receipt`
 * table; `sl_no` is the auto-increment PK.
 *
 * The `flag` is the lifecycle: 'T' = pending, 'CR' (or legacy 'FZ') = finalized, 'E' = exported.
 */
class FirstReceipt extends Model
{
    protected $table = 'first_receipt';
    protected $primaryKey = 'sl_no';
    public $timestamps = false;

    protected $fillable = [
        'draft_no',
        'draft_date',
        'order_no',
        'order_date',
        'amount',
        'date_of_entry',
        'flag',
        'ddocode',
        'type',
        'draw_bank_code',
        'purpose',
        'contribution_type',
        'pension_type',
        'user_id',
        'finalize_date',
    ];

    protected $casts = [
        'draft_date' => 'date',
        'order_date' => 'date',
        'date_of_entry' => 'date',
        'finalize_date' => 'date',
    ];

    /** The DDO that deposited this money (ddocode → ddo_master.ddo_sl). */
    public function ddo(): BelongsTo
    {
        return $this->belongsTo(Ddo::class, 'ddocode', 'ddo_sl');
    }

    /** The bank the draft/receipt was drawn on (draw_bank_code → bank_master.bank_code). */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'draw_bank_code', 'bank_code');
    }

    /** The purpose code (purpose → purpose_master_codes.pid). Named to avoid the `purpose` column. */
    public function purposeCode(): BelongsTo
    {
        return $this->belongsTo(Purpose::class, 'purpose', 'pid');
    }

    /**
     * Search (draft no / order no / receipt no) + status filter.
     * status: '' = all · 'T' = pending · 'F' = finalized (flag in FZ/CR).
     */
    public function scopeFilter(Builder $query, string $search, string $status): Builder
    {
        return $query
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('CAST(draft_no AS TEXT) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(order_no) LIKE ?', [$term])
                        ->orWhereRaw('CAST(sl_no AS TEXT) LIKE ?', [$term]);
                });
            })
            ->when($status === 'T', fn ($q) => $q->where('flag', 'T'))
            ->when($status === 'F', fn ($q) => $q->whereIn('flag', ['FZ', 'CR']));
    }
}
