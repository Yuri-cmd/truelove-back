<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'subject',
        'controller',
        'method',
        'type',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Registrar un email enviado exitosamente
     */
    public static function logSuccess($email, $subject, $type, $controller = null, $method = null)
    {
        return self::create([
            'email' => $email,
            'subject' => $subject,
            'controller' => $controller,
            'method' => $method,
            'type' => $type,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Registrar un email que falló
     */
    public static function logFailure($email, $subject, $type, $errorMessage, $controller = null, $method = null)
    {
        return self::create([
            'email' => $email,
            'subject' => $subject,
            'controller' => $controller,
            'method' => $method,
            'type' => $type,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }
}
