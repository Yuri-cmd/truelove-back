<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalPrioridad extends Model
{
    use HasFactory;

    protected $table = 'local_priorities';

    protected $fillable = [
        'establecimiento_id',
        'prioridad',
        'fecha_actualizacion'
    ];

    protected $casts = [
        'prioridad' => 'integer',
        'fecha_actualizacion' => 'datetime'
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}
