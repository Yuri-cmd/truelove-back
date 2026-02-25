<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'fcm_token',
        'app_name',
        'user_id',
        'user_type',
        'title',
        'body',
        'data',
        'sent_at',
        'received_at',
        'opened_at'
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'opened_at' => 'datetime'
    ];
}
