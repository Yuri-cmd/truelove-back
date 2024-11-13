<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRegistration extends Model
{
    protected $fillable = [
        'name',
        'lastName',
        'businessType',
        'phone',
        'email',
        'verification_code',
        'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}