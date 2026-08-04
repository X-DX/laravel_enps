<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

/**
 * A subscriber = a government employee enrolled in NPS. Maps onto the legacy
 * `allotment_accnt_no` table: one row per subscriber, holding their details plus the
 * allotted account number.
 *
 * Phase A: `id` is the auto-increment PK; the legacy table has no created_at/updated_at
 * (it uses its own entry_date / finalize_date columns instead).
 */
class Subscriber extends Model
{
    protected $table = 'allotment_accnt_no';
    public $timestamps = false;

    protected $casts = [
        'dob' => 'date',
        'doj' => 'date',
        'isactive' => 'boolean',
    ];

    /** The DDO this subscriber belongs to (ddocode → ddo_master.ddo_sl). */
    public function ddo(): BelongsTo
    {
        return $this->belongsTo(Ddo::class, 'ddocode', 'ddo_sl');
    }

    /**
     * The designation. Named `designationMaster` on purpose — the table already has a
     * `designation` COLUMN (the foreign key), and a relationship called `designation`
     * would collide with it (Eloquent would return the column, not the related model).
     */
    public function designationMaster(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation', 'designation_id');
    }

    /** The PRAN for this subscriber's account (pran_no.account_no = account_no). */
    public function pran(): HasOne
    {
        return $this->hasOne(PranNo::class, 'account_no', 'account_no');
    }

    /** Search (name/account no) + status filter — shared by the list and the Excel export. */
    public function scopeFilter(Builder $query, string $search, string $status): Builder
    {
        return $query
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(account_no) LIKE ?', [$term]);
                });
            })
            ->when($status !== '', fn($q) => $q->where('save_flag', $status));
    }
}
