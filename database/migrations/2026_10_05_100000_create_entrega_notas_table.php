<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notas y fotos que dejan los motorizados sobre un lugar de entrega (ej. "timbre
     * malogrado", "casa de reja negra") para que el siguiente que entregue ahí llegue
     * más rápido. Pertenecen al LUGAR (latitud/longitud del pedido), no a un cliente.
     */
    public function up(): void
    {
        Schema::create('entrega_notas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('motorizado_id');
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->text('nota')->nullable();
            $table->string('foto_path')->nullable();
            $table->timestamps();

            $table->index(['latitud', 'longitud']);
            $table->index('motorizado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_notas');
    }
};
