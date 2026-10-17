<?php

namespace App\Models;

use App\Events\PedidoEstadoActualizado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PedidoTracking extends Model
{
    use HasFactory;
    protected $table = 'pedido_trackings';
    protected $fillable = ['pedido_id', 'estado', 'user_id', 'user_type'];

    /**
     * Cualquier controlador que cree un PedidoTracking (hay varios: biker,
     * admin, cancelación, socio...) dispara el broadcast desde un único
     * lugar en vez de tener que acordarse de emitirlo en cada uno.
     *
     * El try/catch es obligatorio: ShouldBroadcastNow transmite en el mismo
     * request (sin cola), así que si Reverb está caído o no se desplegó
     * todavía, el broadcast lanza una excepción real (cURL/connection
     * refused). Sin este catch, actualizar el estado de CUALQUIER pedido
     * —app nueva o vieja, cliente o motorizado— fallaría con 500 cada vez
     * que el socket no esté disponible. El tiempo real es una mejora
     * opcional; nunca debe poder tumbar la operación principal.
     */
    protected static function booted(): void
    {
        static::created(function (PedidoTracking $tracking) {
            try {
                PedidoEstadoActualizado::dispatch((int) $tracking->pedido_id);
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir PedidoEstadoActualizado: ' . $e->getMessage());
            }
        });
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Set traceability fields from request or authenticated user
     */
    public function setTraceability($request)
    {
        $user = $request->user();
        if ($user) {
            $this->user_id = $user->id;
            $this->user_type = $user->role ? $user->role->name : 'admin';
        } elseif ($request->id_cliente) {
            $this->user_id = $request->id_cliente;
            $this->user_type = 'cliente';
        } elseif ($request->id_motorizado) {
            $this->user_id = $request->id_motorizado;
            $this->user_type = 'motorizado';
        }
        return $this;
    }
}
