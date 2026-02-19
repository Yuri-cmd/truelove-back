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
        'latest_version',
        'force_update',
        'url_android',
        'url_ios',
    ];
}
