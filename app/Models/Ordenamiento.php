<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ordenamiento extends Model
{
      protected $fillable = [
        'paciente_id',
        'numero_orden',
        'numero_episodio',
        'numero_documento',
        'fecha_solicitud',
        'hora_solicitud',
        'fuente_orden',
        'prioridad',
        'especialidad_solicitante',
        'profesional_solicitante',
        'diagnostico_1',
        'diagnostico_2',
        'diagnostico_3',
        'sede_solicitante',
        'codigo_diagnostico',
         'documento_prof_solicitante'
    ];
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function prestaciones(): HasMany
    {
        return $this->hasMany(Prestacion::class, 'ordenamiento_id');
    }
}
