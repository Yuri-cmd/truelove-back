<?php

use App\Models\Cliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los clientes que ya existen se dan por validados (no se les pide el código otra vez ni se
     * gasta un mensaje de WhatsApp por cada uno). Los registros nuevos sí deben validar su número.
     */
    public function up(): void
    {
        DB::table('clientes')->orderBy('id')->chunkById(500, function ($clientes) {
            foreach ($clientes as $c) {
                $numero = Cliente::normalizarCelular($c->celular_whatsapp) ?? Cliente::normalizarCelular($c->celular);

                // Sin ningún número no hay nada que validar
                if ($numero === null) {
                    continue;
                }

                DB::table('clientes')->where('id', $c->id)->update([
                    'numero_validado' => true,
                    'numero_validado_celular' => $numero,
                    'numero_validado_en' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('clientes')->update([
            'numero_validado' => false,
            'numero_validado_celular' => null,
            'numero_validado_en' => null,
        ]);
    }
};
