<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
          $table->id();
        $table->string('numero_paciente')->unique();
        $table->string('tipo_documento')->nullable();
        $table->string('numero_documento')->nullable();
        $table->string('primer_nombre')->nullable();
        $table->string('segundo_nombre')->nullable();
        $table->string('apellido')->nullable();
        $table->string('apellido_soltero')->nullable();
        $table->string('sexo')->nullable();
        $table->unsignedSmallInteger('edad')->nullable();
        $table->string('unidad_edad')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
