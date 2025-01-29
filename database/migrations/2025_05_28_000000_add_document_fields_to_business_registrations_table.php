<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentFieldsToBusinessRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('business_registrations', function (Blueprint $table) {
            $table->string('documentType')->after('id')->nullable();
            $table->string('documentNumber')->after('documentType')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('business_registrations', function (Blueprint $table) {
            $table->dropColumn('documentType');
            $table->dropColumn('documentNumber');
        });
    }
}

