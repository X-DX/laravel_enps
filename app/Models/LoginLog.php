<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps onto the legacy `login_log` table (compatibility-first / Phase A).
 */
class LoginLog extends Model
{
    protected $table = 'login_log';

    protected $primaryKey = 'auto_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'login_datetime',
        'sys_ip',
        'sys_os',
    ];

    protected $casts = [
        'login_datetime' => 'datetime',
    ];
}
