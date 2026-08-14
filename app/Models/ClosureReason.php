<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reason an account can be closed (legacy `m_closure_reason`): e.g. Death Case, VRS.
 * A pure lookup table — id + reason, no timestamps.
 */
class ClosureReason extends Model
{
    protected $table = 'm_closure_reason';
    public $timestamps = false;
    protected $fillable = ['reason'];
}
