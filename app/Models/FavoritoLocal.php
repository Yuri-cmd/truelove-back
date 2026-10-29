<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoritoLocal extends Model
{
    protected $table = 'favoritos_locales';
    protected $fillable = ['cliente_id', 'business_registration_id'];
}
