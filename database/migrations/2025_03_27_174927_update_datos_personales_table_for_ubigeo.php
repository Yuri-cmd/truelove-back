<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero agregamos la nueva columna sin la restricción de clave foránea
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->integer('ubigeo_id')->nullable()->after('url_selfie');
        });

        // Ejecutamos la consulta SQL para migrar los datos
        DB::statement('
            UPDATE datos_personales dp
            JOIN distritos d ON dp.distrito_id = d.id
            JOIN ubigeo_inei u ON LOWER(u.nombre) = LOWER(d.nombre) AND u.distrito != "00"
            SET dp.ubigeo_id = u.id_ubigeo
            WHERE dp.ubigeo_id IS NULL
        ');

        // Agregamos la restricción de clave foránea
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->foreign('ubigeo_id')->references('id_ubigeo')->on('ubigeo_inei');
        });

        // Finalmente eliminamos las columnas antiguas
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->dropForeign(['ciudad_id']);
            $table->dropForeign(['distrito_id']);
            $table->dropColumn(['ciudad_id', 'distrito_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero restauramos las columnas antiguas
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->unsignedBigInteger('ciudad_id')->nullable();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->foreign('ciudad_id')->references('id')->on('ciudades');
            $table->foreign('distrito_id')->references('id')->on('distritos');
        });

        // Luego eliminamos la nueva columna
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->dropForeign(['ubigeo_id']);
            $table->dropColumn('ubigeo_id');
        });
    }
};