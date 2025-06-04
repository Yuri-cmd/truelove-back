<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reason',
        'status',
        'requested_at',
        'processed_at',
        'processed_by'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relación con el usuario que solicita la eliminación
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con el usuario que procesa la solicitud (admin)
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scope para obtener solicitudes pendientes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}