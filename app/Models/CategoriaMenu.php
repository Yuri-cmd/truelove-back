<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_id', 'menu_id',
    ];
}
