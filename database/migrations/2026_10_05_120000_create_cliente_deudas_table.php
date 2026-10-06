<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deudas de clientes (ej. pedido que no se pudo entregar por culpa del cliente).
     * Mientras el cliente tenga una deuda 'pendiente' no puede hacer pedidos nuevos.
     */
    public function up(): void
    {
        Schema::create('cliente_deudas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->index();
            $table->unsignedBigInteger('pedido_id')->nullable()->index();
            $table->unsignedBigInteger('motorizado_id')->nullable();   // quién reportó
            $table->decimal('monto', 10, 2);
            $table->string('motivo');
            $table->string('estado', 20)->default('pendiente')->index(); // pendiente | pagado | anulado
            $table->string('registrado_por', 20)->default('admin');       // motorizado | admin
            $table->text('observaciones_admin')->nullable();
            $table->unsignedBigInteger('gestionada_por')->nullable();     // admin que la creó/cerró
            $table->timestamp('gestionada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_deudas');
    }
};
