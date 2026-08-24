<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Purpose lookup for a first-register receipt (legacy `purpose_master_codes`), e.g.
 * pid "D01" → "DEDUCTION FOR JAN". `pid` is a hand-assigned string code (the PK).
 */
class Purpose extends Model
{
    protected $table = 'purpose_master_codes';
    protected $primaryKey = 'pid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['pid', 'purpose'];
}
