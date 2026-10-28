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
        Schema::create('prestacions', function (Blueprint $table) {
      $table->id();
        $table->foreignId('ordenamiento_id')
            ->constrained('ordenamientos')
            ->cascadeOnDelete();

        $table->string('id_posicion_orden');
        $table->string('univoco_prestacion')->nullable();
        $table->string('cups')->nullable();
        $table->text('descripcion')->nullable();
        $table->string('tipo_orden')->nullable();
        $table->string('estado')->nullable();
        $table->string('sede_ejecucion')->nullable();
        $table->string('fecha_realizacion')->nullable();
        $table->string('hora_realizacion')->nullable();
        $table->string('profesional_ejecutor')->nullable();
        $table->string('especialidad_ejecutora')->nullable();
        $table->timestamps();

        $table->unique(['ordenamiento_id', 'id_posicion_orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestacions');
    }
};
