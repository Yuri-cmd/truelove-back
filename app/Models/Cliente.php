<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    use HasFactory, HasApiTokens;
    protected $table = 'clientes';
    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'genero',
        'email',
        'documento',
        'nacionalidad',
        'celular',
        'celular_whatsapp',
        'dni_photo',
        'selfie_photo',
        'foto_perfil',
        'password',
        'token_fmc',
        'numero_validado',
        'numero_validado_celular',
        'numero_validado_en',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'numero_validado' => 'boolean',
        'numero_validado_en' => 'datetime',
    ];

    /** Últimos 9 dígitos de un número (celular peruano sin prefijo). */
    public static function normalizarCelular(?string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        return $digitos === '' ? null : substr($digitos, -9);
    }

    /** El número que debe estar validado: el de WhatsApp, o el celular si no tiene WhatsApp. */
    public function numeroAValidar(): ?string
    {
        return self::normalizarCelular($this->celular_whatsapp) ?? self::normalizarCelular($this->celular);
    }

    /** Validado Y sigue siendo el mismo número que se validó. */
    public function numeroEstaValidado(): bool
    {
        return $this->numero_validado
            && $this->numero_validado_celular !== null
            && $this->numero_validado_celular === $this->numeroAValidar();
    }

    /** Si cambió el número de contacto, deja de contar como validado. */
    public function sincronizarValidacion(): void
    {
        if ($this->numero_validado && $this->numero_validado_celular !== $this->numeroAValidar()) {
            $this->numero_validado = false;
            $this->numero_validado_en = null;
            $this->save();
        }
    }
}
