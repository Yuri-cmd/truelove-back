<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'app_name',
        'error_message',
        'stack_trace',
        'user_id',
        'device_info',
        'url',
        'method',
        'request_data',
    ];

    protected $casts = [
        'device_info' => 'array',
        'request_data' => 'array',
    ];
}
