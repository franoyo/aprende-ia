<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    protected $fillable = [
        'numero_paciente',
        'tipo_documento',
        'numero_documento',
        'primer_nombre',
        'segundo_nombre',
        'apellido',
        'apellido_soltero',
        'sexo',
        'edad',
        'unidad_edad',
    ];

      public function ordenamientos(): HasMany
    {
        return $this->hasMany(Ordenamiento::class);
    }
}
