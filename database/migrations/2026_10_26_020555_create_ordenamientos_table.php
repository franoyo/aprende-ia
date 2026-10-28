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
        Schema::create('ordenamientos', function (Blueprint $table) {
           $table->id();
        $table->foreignId('paciente_id')
            ->constrained('pacientes')
            ->cascadeOnDelete();

        $table->string('numero_orden');
        $table->string('numero_episodio')->nullable();
        $table->string('numero_documento')->nullable();
        $table->string('fecha_solicitud')->nullable();
        $table->string('hora_solicitud')->nullable();
        $table->string('fuente_orden')->nullable();
        $table->string('prioridad')->nullable();
        $table->string('especialidad_solicitante')->nullable();
        $table->string('profesional_solicitante')->nullable();
        $table->string('diagnostico_1')->nullable();
        $table->string('diagnostico_2')->nullable();
        $table->string('diagnostico_3')->nullable();
         $table->string('codigo_diagnostico')->nullable();
             $table->string('documento_prof_solicitante')->nullable();
        $table->string('sede_solicitante')->nullable();
        $table->timestamps();

        $table->unique(['paciente_id', 'numero_orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenamientos');
    }
};
