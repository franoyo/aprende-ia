<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Models\Paciente;
use App\Models\Ordenamiento;
use App\Models\Prestacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ImportacionExcelController extends Controller
{
     public function index()
    {
        return view('importar-excel');
    }

    public function previsualizar(Request $request)
    {
        $request->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:32000',
            ],
        ]);

        $libro = IOFactory::load(
            $request->file('archivo')->getRealPath()
        );

        $hoja = $libro->getActiveSheet();
        $ultimaFila = $hoja->getHighestDataRow();
        $ultimaColumna = Coordinate::columnIndexFromString(
            $hoja->getHighestDataColumn()
        );

        $encabezados = [];
        $conteos = [
            'ordenes_principales' => 0,
            'prestaciones_adicionales' => 0,
            'resultados_auxiliares' => 0,
            'otras_filas' => 0,
        ];

        $muestra = [];
        $ordenActual = null;
        $pacienteActual = null;

        for ($fila = 1; $fila <= $ultimaFila; $fila++) {
            $valores = $hoja->rangeToArray(
                "A{$fila}:" .
                Coordinate::stringFromColumnIndex($ultimaColumna) .
                "{$fila}",
                null,
                true,
                true,
                false
            )[0];

            if ($fila === 1) {
                foreach ($valores as $indice => $valor) {
                    if ($valor !== null && trim((string) $valor) !== '') {
                        $encabezados[
                            Coordinate::stringFromColumnIndex($indice + 1)
                        ] = trim((string) $valor);
                    }
                }

                continue;
            }

            $celda = fn (string $columna) => trim((string) (
                $valores[
                    Coordinate::columnIndexFromString($columna) - 1
                ] ?? ''
            ));

            $esResultado =
                strtoupper($celda('BX')) === 'RESULTADO' ||
                strtoupper($celda('BY')) === 'RESULTADO';

            $numeroPaciente = $celda('D');
            $numeroOrden = $celda('V');
            $posicion = $celda('AR');
            $prestacion = $celda('AY');

            if ($esResultado) {
                $tipo = 'resultados_auxiliares';
            } elseif (
                $numeroPaciente !== '' && $numeroPaciente !== '#' ||
                $numeroOrden !== '' && $numeroOrden !== '#'
            ) {
                $tipo = 'ordenes_principales';

                if ($numeroPaciente !== '' && $numeroPaciente !== '#') {
                    $pacienteActual = $numeroPaciente;
                }

                if ($numeroOrden !== '' && $numeroOrden !== '#') {
                    $ordenActual = $numeroOrden;
                }
            } elseif (
                ($posicion !== '' && $posicion !== '#') ||
                ($prestacion !== '' && $prestacion !== '#')
            ) {
                $tipo = 'prestaciones_adicionales';
            } else {
                $tipo = 'otras_filas';
            }

            $conteos[$tipo]++;

            if (count($muestra) < 20) {
                $muestra[] = [
                    'fila_excel' => $fila,
                    'tipo' => $tipo,
                    'paciente' => $numeroPaciente ?: $pacienteActual,
                    'orden' => $numeroOrden ?: $ordenActual,
                    'posicion' => $posicion,
                    'cups' => $celda('AZ'),
                    'descripcion' => trim(
                        $celda('BA') . ' ' .
                        $celda('BB') . ' ' .
                        $celda('BC')
                    ),
                ];
            }
        }

        
$pacientesUnicos = [];
$ordenesUnicas = [];
$posicionesUnicas = [];

for ($fila = 2; $fila <= $ultimaFila; $fila++) {
    $v = $hoja->rangeToArray(
        "A{$fila}:CD{$fila}",
        null,
        true,
        true,
        false
    )[0];

    $celda = fn (string $columna) => trim((string) (
        $v[
            Coordinate::columnIndexFromString($columna) - 1
        ] ?? ''
    ));

    $paciente = $celda('D');
    $orden = $celda('V');
    $posicion = $celda('AR');

    if ($paciente !== '' && $paciente !== '#') {
        $pacientesUnicos[$paciente] = true;
    }

    if ($orden !== '' && $orden !== '#') {
        $ordenesUnicas[$orden] = true;
    }

    if ($posicion !== '' && $posicion !== '#') {
        $posicionesUnicas[$posicion] = true;
    }
}

$identificadores = [
    'pacientes_unicos' => count($pacientesUnicos),
    'ordenes_unicas' => count($ordenesUnicas),
    'posiciones_unicas' => count($posicionesUnicas),
];


        return response()->json([
            'identificadores_unicos' => $identificadores,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'hoja' => $hoja->getTitle(),
            'filas_excel' => $ultimaFila,
            'columnas_excel' => $ultimaColumna,
            'encabezados_detectados' => $encabezados,
            'conteos' => $conteos,
            'muestra' => $muestra,
            'mensaje' => 'Previsualización completada. No se guardaron registros en la base de datos.',
        ]);
    }





public function prev(Request $request)
{
    $request->validate([
        'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:32000'],
    ]);
    ini_set('memory_limit', '2048M');
        set_time_limit(0);
    $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load(
        $request->file('archivo')->getRealPath()
    );

    $hoja = $libro->getActiveSheet();
    $ultimaFila = $hoja->getHighestDataRow();

    $pacientes = [];
    $ordenes = [];
    $prestaciones = [];
    $errores = [];
    $prestacionesAncla = [];
    $filasOmitidas = 0;

    $pacienteActual = null;
    $ordenActual = null;
    $ordenPaciente = [];
    $sedesPorOrden = [];

    $limpiar = function ($valor) {
        $valor = trim((string) ($valor ?? ''));

        return $valor === '' || $valor === '#'
            ? null
            : $valor;
    };

    for ($fila = 2; $fila <= $ultimaFila; $fila++) {
        $v = $hoja->rangeToArray(
            "A{$fila}:CD{$fila}",
            null,
            true,
            true,
            false
        )[0];

        $celda = function (string $columna) use ($v, $limpiar) {
            $indice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $columna
            ) - 1;

            return $limpiar($v[$indice] ?? null);
        };

        // Ignorar filas auxiliares de resultados.
        if (
            strtoupper((string) ($celda('BX') ?? '')) === 'RESULTADO' ||
            strtoupper((string) ($celda('BY') ?? '')) === 'RESULTADO'
        ) {
            continue;
        }

        $numeroPaciente = $celda('D');
        $numeroOrden = $celda('V');
        $posicion = $celda('AR');
        $sedeSolicitante = $celda('AA');

        // Actualizar contexto del paciente.
        if ($numeroPaciente !== null) {
            if ($pacienteActual !== $numeroPaciente) {
                $ordenActual = null;
            }

            $pacienteActual = $numeroPaciente;
        }

        // Actualizar contexto de la orden.
        if ($numeroOrden !== null) {
            if ($pacienteActual === null) {
                $errores[] = [
                    'fila' => $fila,
                    'motivo' => 'Hay una orden sin paciente identificable.',
                    'orden' => $numeroOrden,
                ];

                $ordenActual = null;
                $filasOmitidas++;
                continue;
            }

            $ordenActual = $numeroOrden;
        }

        if ($pacienteActual === null || $ordenActual === null) {
            if ($posicion !== null) {
                $errores[] = [
                    'fila' => $fila,
                    'motivo' => 'La prestación no tiene paciente u orden válidos.',
                    'paciente' => $pacienteActual,
                    'orden' => $ordenActual,
                    'posicion' => $posicion,
                ];

                $filasOmitidas++;
            }

            continue;
        }

        $claveOrden = $pacienteActual . '|' . $ordenActual;

        // Validar que una orden no pertenezca a pacientes diferentes.
        if (
            isset($ordenPaciente[$ordenActual]) &&
            $ordenPaciente[$ordenActual] !== $pacienteActual
        ) {
            $errores[] = [
                'fila' => $fila,
                'motivo' => 'El mismo número de orden aparece con pacientes diferentes.',
                'orden' => $ordenActual,
                'paciente_actual' => $pacienteActual,
                'paciente_anterior' => $ordenPaciente[$ordenActual],
            ];

            $filasOmitidas++;
            continue;
        }

        $ordenPaciente[$ordenActual] = $pacienteActual;

        $pacientes[$pacienteActual] = true;
        $ordenes[$claveOrden] = true;

        // Registrar y validar la sede solicitante de la columna AA.
        if ($sedeSolicitante !== null) {
            if (
                isset($sedesPorOrden[$claveOrden]) &&
                $sedesPorOrden[$claveOrden] !== $sedeSolicitante
            ) {
                $errores[] = [
                    'fila' => $fila,
                    'motivo' => 'La misma orden aparece con diferentes sedes solicitantes.',
                    'paciente' => $pacienteActual,
                    'orden' => $ordenActual,
                    'sede_anterior' => $sedesPorOrden[$claveOrden],
                    'sede_actual' => $sedeSolicitante,
                ];
            } else {
                $sedesPorOrden[$claveOrden] = $sedeSolicitante;
            }
        }

        // Sin posición no se genera una prestación.
        if ($posicion === null) {
            continue;
        }

        $clavePrestacion = $claveOrden . '|' . $posicion;

        $descripcion = mb_strtoupper(
            trim(
                ($celda('BA') ?? '') . ' ' .
                ($celda('BB') ?? '') . ' ' .
                ($celda('BC') ?? '')
            ),
            'UTF-8'
        );

        // Detectar prestaciones ancla.
        $esPrestacionAncla =
            str_contains($descripcion, 'PRESTACIÓN ANCLA CIRUGÍA') ||
            str_contains($descripcion, 'PRESTACION ANCLA CIRUGIA');

        if ($esPrestacionAncla) {
            $prestacionesAncla[] = [
                'fila' => $fila,
                'paciente' => $pacienteActual,
                'orden' => $ordenActual,
                'posicion' => $posicion,
                'descripcion' => trim($descripcion),
            ];

            continue;
        }

        $datosPrestacion = [
            'univoco' => $celda('AY'),
            'cups' => $celda('AZ'),
            'descripcion' => trim(
                ($celda('BA') ?? '') . ' ' .
                ($celda('BB') ?? '') . ' ' .
                ($celda('BC') ?? '')
            ),
            'tipo_orden' => $celda('AT'),
            'estado' => $celda('AU'),
        ];

        if (isset($prestaciones[$clavePrestacion])) {
            $anterior = $prestaciones[$clavePrestacion];

            if ($anterior !== $datosPrestacion) {
                $errores[] = [
                    'fila' => $fila,
                    'motivo' => 'La misma posición aparece con datos diferentes.',
                    'orden' => $ordenActual,
                    'posicion' => $posicion,
                    'datos_anteriores' => $anterior,
                    'datos_actuales' => $datosPrestacion,
                ];
            }

            continue;
        }

        $prestaciones[$clavePrestacion] = $datosPrestacion;
    }

    $ordenesConSede = count($sedesPorOrden);
    $ordenesSinSede = count($ordenes) - $ordenesConSede;

    return response()->json([
        'mensaje' => 'Validación completada. No se guardaron registros.',

        'pacientes_detectados' => count($pacientes),
        'ordenamientos_detectados' => count($ordenes),
        'prestaciones_detectadas' => count($prestaciones),

        'ordenamientos_con_sede_solicitante' => $ordenesConSede,
        'ordenamientos_sin_sede_solicitante' => $ordenesSinSede,
        'sedes_solicitantes_detectadas' => array_values(
            array_unique(array_values($sedesPorOrden))
        ),

        'prestaciones_ancla_detectadas' => count($prestacionesAncla),
        'detalle_anclas' => array_slice($prestacionesAncla, 0, 20),

        'filas_omitidas' => $filasOmitidas,
        'errores_detectados' => count($errores),
        'detalle_errores' => array_slice($errores, 0, 50),

        'nota' => 'Es una validación preliminar. No se guardaron registros.',
    ]);
}



public function importar(Request $request)
{
    $request->validate([
        'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:32000'],
    ]);

    try {
        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load(
            $request->file('archivo')->getRealPath()
        );

        $hoja = $libro->getActiveSheet();
        $ultimaFila = $hoja->getHighestDataRow();

        $ultimaColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $hoja->getHighestDataColumn()
        );

        $ultimaLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
            $ultimaColumna
        );

        $limpiar = function ($valor) {
            $valor = trim((string) ($valor ?? ''));

            return $valor === '' || $valor === '#'
                ? null
                : $valor;
        };

        $procesados = [
            'pacientes' => 0,
            'ordenamientos' => 0,
            'prestaciones' => 0,
            'anclas_omitidas' => 0,
            'resultados_omitidos' => 0,
            'duplicados_identicos_omitidos' => 0,
        ];

        $filasOmitidas = 0;
        $ordenPacienteArchivo = [];
        $prestacionesArchivo = [];
        $sedesOrdenArchivo = [];

        /*
         * PRIMERA PASADA:
         * Validar y recopilar las sedes de cada orden.
         */
        $pacienteActual = null;
        $ordenActual = null;
        $sedeActual = null;

        for ($fila = 2; $fila <= $ultimaFila; $fila++) {
            $v = $hoja->rangeToArray(
                "A{$fila}:{$ultimaLetra}{$fila}",
                null,
                true,
                true,
                false
            )[0];

            $celda = function (string $columna) use ($v, $limpiar) {
                $indice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                    $columna
                ) - 1;

                return $limpiar($v[$indice] ?? null);
            };

            if (
                strtoupper((string) ($celda('BX') ?? '')) === 'RESULTADO' ||
                strtoupper((string) ($celda('BY') ?? '')) === 'RESULTADO'
            ) {
                $procesados['resultados_omitidos']++;
                continue;
            }

            $numeroPaciente = $celda('D');
            $numeroOrden = $celda('V');
            $posicion = $celda('AR');
            $sedeFila = $celda('AA');

            if ($numeroPaciente !== null) {
                if ($pacienteActual !== $numeroPaciente) {
                    $ordenActual = null;
                    $sedeActual = null;
                }

                $pacienteActual = $numeroPaciente;
            }

            if ($numeroOrden !== null) {
                if ($pacienteActual === null) {
                    throw new \RuntimeException(
                        "Orden sin paciente identificable en la fila {$fila}."
                    );
                }

                if ($ordenActual !== $numeroOrden) {
                    $ordenActual = $numeroOrden;
                    $sedeActual = null;
                }
            }

            if ($sedeFila !== null) {
                $sedeActual = $sedeFila;
            }

            if ($pacienteActual === null || $ordenActual === null) {
                if ($posicion !== null) {
                    throw new \RuntimeException(
                        "Prestación sin paciente u orden válidos en la fila {$fila}."
                    );
                }

                continue;
            }

            $claveOrden = $pacienteActual . '|' . $ordenActual;

            if (
                isset($ordenPacienteArchivo[$ordenActual]) &&
                $ordenPacienteArchivo[$ordenActual] !== $pacienteActual
            ) {
                throw new \RuntimeException(
                    "La orden {$ordenActual} aparece asociada a diferentes pacientes. " .
                    "Fila {$fila}."
                );
            }

            $ordenPacienteArchivo[$ordenActual] = $pacienteActual;

            // Cada orden conserva su propia sede solicitante.
            if ($sedeActual !== null) {
                if (
                    isset($sedesOrdenArchivo[$claveOrden]) &&
                    $sedesOrdenArchivo[$claveOrden] !== $sedeActual
                ) {
                    throw new \RuntimeException(
                        "La orden {$ordenActual} tiene más de una sede solicitante. " .
                        "Fila {$fila}."
                    );
                }

                $sedesOrdenArchivo[$claveOrden] = $sedeActual;
            }

            $descripcionMayuscula = mb_strtoupper(
                trim(
                    ($celda('BA') ?? '') . ' ' .
                    ($celda('BB') ?? '') . ' ' .
                    ($celda('BC') ?? '')
                ),
                'UTF-8'
            );

            if (
                str_contains($descripcionMayuscula, 'PRESTACIÓN ANCLA CIRUGÍA') ||
                str_contains($descripcionMayuscula, 'PRESTACION ANCLA CIRUGIA')
            ) {
                $procesados['anclas_omitidas']++;
                continue;
            }

            if ($posicion === null) {
                continue;
            }

            $descripcion = collect([
                $celda('BA'),
                $celda('BB'),
                $celda('BC'),
            ])->filter()->implode(' ');

            $datosPrestacion = [
                'univoco_prestacion' => $celda('AY'),
                'cups' => $celda('AZ'),
                'descripcion' => $descripcion,
                'tipo_orden' => $celda('AT'),
                'estado' => $celda('AU'),
                'sede_ejecucion' => $celda('BE'),
                'fecha_realizacion' => $celda('BF'),
                'hora_realizacion' => $celda('BG'),
                'profesional_ejecutor' => $celda('BH'),
                'especialidad_ejecutora' => $celda('BN'),
            ];

            $clavePrestacion = $claveOrden . '|' . $posicion;

            if (isset($prestacionesArchivo[$clavePrestacion])) {
                if (
                    $prestacionesArchivo[$clavePrestacion]['datos']
                    !== $datosPrestacion
                ) {
                    throw new \RuntimeException(
                        "La orden {$ordenActual}, posición {$posicion}, tiene datos " .
                        "diferentes en las filas " .
                        $prestacionesArchivo[$clavePrestacion]['fila'] .
                        " y {$fila}. No se importó nada."
                    );
                }

                $procesados['duplicados_identicos_omitidos']++;
                continue;
            }

            $prestacionesArchivo[$clavePrestacion] = [
                'fila' => $fila,
                'datos' => $datosPrestacion,
            ];
        }

        /*
         * Verificar que cada orden tenga una sede solicitante.
         */
        $ordenesSinSede = [];

        foreach ($ordenPacienteArchivo as $numeroOrden => $numeroPaciente) {
            $clave = $numeroPaciente . '|' . $numeroOrden;

            if (!isset($sedesOrdenArchivo[$clave])) {
                $ordenesSinSede[] = $numeroOrden;
            }
        }

        if (count($ordenesSinSede) > 0) {
            throw new \RuntimeException(
                'Hay órdenes sin sede solicitante en la columna AA. ' .
                'Ejemplos: ' .
                implode(', ', array_slice($ordenesSinSede, 0, 10)) .
                '. No se importó nada.'
            );
        }

        /*
         * SEGUNDA PASADA:
         * Guardar dentro de una transacción.
         */
        DB::transaction(function () use (
            $hoja,
            $ultimaFila,
            $ultimaLetra,
            $limpiar,
            &$procesados,
            &$filasOmitidas,
            $prestacionesArchivo,
            $sedesOrdenArchivo
        ) {
            $pacienteActual = null;
            $ordenActual = null;

            for ($fila = 2; $fila <= $ultimaFila; $fila++) {
                $v = $hoja->rangeToArray(
                    "A{$fila}:{$ultimaLetra}{$fila}",
                    null,
                    true,
                    true,
                    false
                )[0];

                $celda = function (string $columna) use ($v, $limpiar) {
                    $indice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                        $columna
                    ) - 1;

                    return $limpiar($v[$indice] ?? null);
                };

                if (
                    strtoupper((string) ($celda('BX') ?? '')) === 'RESULTADO' ||
                    strtoupper((string) ($celda('BY') ?? '')) === 'RESULTADO'
                ) {
                    continue;
                }

                $numeroPaciente = $celda('D');
                $numeroOrden = $celda('V');
                $posicion = $celda('AR');

                if ($numeroPaciente !== null) {
                    if ($pacienteActual !== $numeroPaciente) {
                        $ordenActual = null;
                    }

                    $pacienteActual = $numeroPaciente;
                }

                if ($numeroOrden !== null) {
                    $ordenActual = $numeroOrden;
                }

                $descripcionMayuscula = mb_strtoupper(
                    trim(
                        ($celda('BA') ?? '') . ' ' .
                        ($celda('BB') ?? '') . ' ' .
                        ($celda('BC') ?? '')
                    ),
                    'UTF-8'
                );

                if (
                    str_contains($descripcionMayuscula, 'PRESTACIÓN ANCLA CIRUGÍA') ||
                    str_contains($descripcionMayuscula, 'PRESTACION ANCLA CIRUGIA')
                ) {
                    continue;
                }

                if ($posicion === null) {
                    continue;
                }

                if ($pacienteActual === null || $ordenActual === null) {
                    $filasOmitidas++;
                    continue;
                }

                $claveOrden = $pacienteActual . '|' . $ordenActual;
                $clavePrestacion = $claveOrden . '|' . $posicion;

                $registroArchivo = $prestacionesArchivo[$clavePrestacion] ?? null;

                if (
                    $registroArchivo === null ||
                    $registroArchivo['fila'] !== $fila
                ) {
                    continue;
                }

                $datosPaciente = [
                    'tipo_documento' => $celda('I'),
                    'numero_documento' => $celda('J'),
                    'primer_nombre' => $celda('G'),
                    'segundo_nombre' => $celda('H'),
                    'apellido' => $celda('E'),
                    'apellido_soltero' => $celda('F'),
                    'sexo' => $celda('L'),
                    'unidad_edad' => $celda('P'),
                ];

                $edad = $celda('O');

                if ($edad !== null && is_numeric($edad)) {
                    $datosPaciente['edad'] = (int) $edad;
                }

                $paciente = Paciente::firstOrNew([
                    'numero_paciente' => $pacienteActual,
                ]);

                foreach ($datosPaciente as $campo => $valor) {
                    if ($valor !== null) {
                        $paciente->{$campo} = $valor;
                    }
                }

                $paciente->save();
                $procesados['pacientes']++;

                $datosOrden = [
                    'numero_episodio' => $celda('C'),
                    'numero_documento' => $celda('X'),
                    'fecha_solicitud' => $celda('AB'),
                    'hora_solicitud' => $celda('AC'),
                    'fuente_orden' => $celda('W'),
                    'prioridad' => $celda('Y'),
                    'especialidad_solicitante' => $celda('AK'),
                    'profesional_solicitante' => $celda('AE'),
                    'diagnostico_1' => $celda('AM'),
                    'diagnostico_2' => $celda('AO'),
                    'diagnostico_3' => $celda('AQ'),
                    'sede_solicitante' => $sedesOrdenArchivo[$claveOrden],
                        'codigo_diagnostico'=>$celda('AL'),
         'documento_prof_solicitante'=>$celda('AG')
                ];

                $orden = Ordenamiento::firstOrNew([
                    'paciente_id' => $paciente->id,
                    'numero_orden' => $ordenActual,
                ]);

                foreach ($datosOrden as $campo => $valor) {
                    if ($valor !== null) {
                        $orden->{$campo} = $valor;
                    }
                }

                $orden->save();
                $procesados['ordenamientos']++;

                $prestacion = Prestacion::firstOrNew([
                    'ordenamiento_id' => $orden->id,
                    'id_posicion_orden' => $posicion,
                ]);

                foreach ($registroArchivo['datos'] as $campo => $valor) {
                    if ($valor !== null) {
                        $prestacion->{$campo} = $valor;
                    }
                }

                $prestacion->save();
                $procesados['prestaciones']++;
            }
        }, 3);

        $totalOrdenamientos = Ordenamiento::count();
        $conSede = Ordenamiento::whereNotNull('sede_solicitante')
            ->where('sede_solicitante', '<>', '')
            ->count();

        return response()->json([
            'mensaje' => 'Importación completada.',
            'registros_procesados' => $procesados,
            'ordenamientos_con_sede_solicitante' => $conSede,
            'ordenamientos_sin_sede_solicitante' => $totalOrdenamientos - $conSede,
            'filas_omitidas' => $filasOmitidas,
            'total_en_base_de_datos' => [
                'pacientes' => Paciente::count(),
                'ordenamientos' => $totalOrdenamientos,
                'prestaciones' => Prestacion::count(),
            ],
        ]);
    } catch (\Throwable $e) {
        Log::error('Error en importación Excel', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'La importación se canceló o no pudo completarse.',
            'detalle' => $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Ocurrió un error al procesar el archivo. Revisa el registro de Laravel.',
        ], 422);
    }
}




}







