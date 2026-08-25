<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
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
    use OwnedByUser;

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
        'other_purpose',
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
     * Human-readable purpose: the master label, or the operator's free text when "OTHERS".
     * Used by the list, detail page, Excel and PDF so they all read the same.
     */
    public function purposeLabel(): string
    {
        if ($this->purpose === 'OTH') {
            return $this->other_purpose ?: 'Others';
        }

        return $this->purposeCode?->purpose ?? (string) $this->purpose;
    }

    /**
     * The three-state lifecycle label, matching the legacy screens exactly:
     *   T  → just entered · CR → finalized from receipt, awaiting CR generation · FZ → CR generated.
     * Kept here (not in a view) so the list, detail page, Excel and PDF all read the same text.
     */
    public function statusLabel(): string
    {
        return match ($this->flag) {
            'T' => 'Pending at First Receipt',
            'CR' => 'Pending at CR Generation',
            'FZ' => 'Finalized (CR Generated)',
            default => (string) $this->flag,
        };
    }

    /** A colour-tone keyword for the status badge; the view maps it to CSS classes. */
    public function statusTone(): string
    {
        return match ($this->flag) {
            'T' => 'amber',
            'CR' => 'sky',
            'FZ' => 'emerald',
            default => 'slate',
        };
    }

    /**
     * Search (draft no / order no / receipt no) + status filter.
     * status: '' = all · 'T' = pending · 'CR'/'FZ' = one finalized stage · 'F' = both finalized (FZ+CR).
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
            ->when(in_array($status, ['T', 'CR', 'FZ'], true), fn ($q) => $q->where('flag', $status))
            ->when($status === 'F', fn ($q) => $q->whereIn('flag', ['FZ', 'CR']));
    }
}
