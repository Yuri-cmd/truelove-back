<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteDeletionRequest extends Model
{
    use HasFactory;

    protected $table = 'cliente_deletion_requests';

    protected $fillable = [
        'cliente_id',
        'request_id',
        'motivo',
        'status',
        'verification_code',
        'code_verified_at',
        'requested_at',
        'processed_at',
        'processed_by',
        'admin_notes',
    ];

    protected $casts = [
        'code_verified_at' => 'datetime',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relación con Cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    // Relación con el admin que procesó
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
