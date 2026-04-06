<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoTracking extends Model
{
    use HasFactory;
    protected $table = 'pedido_trackings';
    protected $fillable = ['pedido_id', 'estado', 'user_id', 'user_type'];

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
