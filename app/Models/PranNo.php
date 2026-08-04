<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A PRAN (Permanent Retirement Account Number) — maps onto the legacy `pran_no` table.
 * Linked to a subscriber by the account number.
 */
class PranNo extends Model
{
    protected $table = 'pran_no';

    protected $primaryKey = 'account_no';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;
}
