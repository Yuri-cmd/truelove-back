<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocPdfExtranjeroTable extends Migration
{
    public function up()
    {
        Schema::create('doc_pdf_extranjero', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_registration_id');
            $table->string('antecedentes_penales_pdf');
            $table->string('antecedentes_policiales_pdf');
            $table->timestamps();

            $table->foreign('business_registration_id')
                  ->references('id')
                  ->on('business_registrations')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('doc_pdf_extranjero');
    }
}