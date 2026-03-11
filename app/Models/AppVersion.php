<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $table = 'app_versions';

    protected $fillable = [
        'app_name',
        'min_version',
        'min_version_android',
        'min_version_ios',
        'latest_version',
        'latest_version_android',
        'latest_version_ios',
        'force_update',
        'force_update_android',
        'force_update_ios',
        'url_android',
        'url_ios',
    ];

    protected $casts = [
        'force_update' => 'boolean',
        'force_update_android' => 'boolean',
        'force_update_ios' => 'boolean',
    ];
}
