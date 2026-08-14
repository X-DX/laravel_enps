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
    protected $fillable = [
        // typed by the operator
        'name',
        'father_name',
        'mother_name',
        'single_mother_flag',
        'appnt_ord_no',
        'doapptorder',
        'dob',
        'doj',
        'dor',
        'designation',
        'nameofdept',
        'pension_type',
        'pay',
        'ddocode',
        'name_nominee',
        'name_nominee2',
        'name_nominee3',
        'starting_month',
        'starting_fin_year',
        // system-generated
        'account_no',
        'save_flag',
        'user_id',
        'flag_pt',
        'closure_reason_id',
        'entry_date',
        'finalize_date',
        'isactive',
    ];
    protected $table = 'allotment_accnt_no';
    public $timestamps = false;

    protected $casts = [
        'dob' => 'date',
        'doj' => 'date',
        'dor' => 'date',
        'doapptorder' => 'date',
        'entry_date' => 'date',
        'finalize_date' => 'date',
        'closure_date' => 'datetime',
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

    /** Why this account was closed (closure_reason_id → m_closure_reason.id). Null if open. */
    public function closureReason(): BelongsTo
    {
        return $this->belongsTo(ClosureReason::class, 'closure_reason_id', 'id');
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

    /** Closed accounts (finalized + inactive) with an optional name/account-no search. */
    public function scopeClosedFilter(Builder $query, string $search): Builder
    {
        return $query
            ->where('save_flag', 'F')
            ->where('isactive', false)
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->where(function ($w) use ($term) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(account_no) LIKE ?', [$term]);
                });
            });
    }
}
