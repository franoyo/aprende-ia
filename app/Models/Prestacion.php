<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prestacion extends Model
{

protected $table = 'prestacions';
    protected $fillable = [
        'ordenamiento_id',
        'id_posicion_orden',
        'univoco_prestacion',
        'cups',
        'descripcion',
        'tipo_orden',
        'estado',
        'sede_ejecucion',
        'fecha_realizacion',
        'hora_realizacion',
        'profesional_ejecutor',
        'especialidad_ejecutora',
    ];

    public function ordenamiento(): BelongsTo
    {
        return $this->belongsTo(Ordenamiento::class);
    }
}
