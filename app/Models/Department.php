<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master list of departments — maps onto the legacy `department` table (dept_code, dept_name).
 * A tiny reference table (29 rows); we look these up by dept_code.
 */
class Department extends Model
{
    protected $table = 'department';
    protected $primaryKey = 'dept_code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['dept_code', 'dept_name'];
}
