<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Ordenamiento;

class DashboardController extends Controller
{

   /**
     * Vista principal del dashboard.
     */
    public function index()
    {
        return view('index');
    }

    /**
     * Estadísticas y datos para las gráficas.
     */
    public function estadisticas(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        $ordenes = $this->consultaOrdenes($filtros);

        $tarjetas = [
            'pacientes' => (clone $ordenes)
                ->distinct()
                ->count('ordenamientos.paciente_id'),

            'ordenamientos' => (clone $ordenes)
                ->count('ordenamientos.id'),

            'prestaciones' => DB::table('prestacions')
                ->join(
                    'ordenamientos',
                    'ordenamientos.id',
                    '=',
                    'prestacions.ordenamiento_id'
                )
                ->whereIn(
                    'ordenamientos.id',
                    (clone $ordenes)->select('ordenamientos.id')
                )
                ->count('prestacions.id'),
        ];

        $porFecha = (clone $ordenes)
            ->whereNotNull('ordenamientos.fecha_solicitud')
            ->selectRaw("
                STR_TO_DATE(
                    ordenamientos.fecha_solicitud,
                    '%d.%m.%Y'
                ) AS fecha,
                COUNT(*) AS total
            ")
            ->groupByRaw("
                STR_TO_DATE(
                    ordenamientos.fecha_solicitud,
                    '%d.%m.%Y'
                )
            ")
            ->orderBy('fecha')
            ->get();

        $porSede = (clone $ordenes)
            ->selectRaw("
                COALESCE(
                    NULLIF(ordenamientos.sede_solicitante, ''),
                    'Sin sede'
                ) AS etiqueta,
                COUNT(*) AS total
            ")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $porEspecialidad = (clone $ordenes)
            ->selectRaw("
                COALESCE(
                    NULLIF(
                        ordenamientos.especialidad_solicitante,
                        ''
                    ),
                    'Sin especialidad'
                ) AS etiqueta,
                COUNT(*) AS total
            ")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $porPrioridad = (clone $ordenes)
            ->selectRaw("
                COALESCE(
                    NULLIF(ordenamientos.prioridad, ''),
                    'Sin prioridad'
                ) AS etiqueta,
                COUNT(*) AS total
            ")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'tarjetas' => $tarjetas,

            'graficas' => [
                'por_fecha' => $porFecha,
                'por_sede_solicitante' => $porSede,
                'por_especialidad' => $porEspecialidad,
                'por_prioridad' => $porPrioridad,
            ],
        ]);
    }

    /**
     * Listado paginado de ordenamientos.
     */
    public function ordenes(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        $consulta = $this->consultaOrdenes($filtros);

        /*
         * Búsqueda general:
         * - Número de orden.
         * - Número de episodio.
         * - Documento del paciente.
         * - Número de paciente.
         * - Nombres y apellidos.
         * - CUPS.
         * - Descripción de la prestación.
         */
        if (!empty($filtros['buscar'])) {
            $buscar = $filtros['buscar'];
            $termino = "%{$buscar}%";

            $consulta->where(function ($q) use ($termino) {
                $q->where(
                    'ordenamientos.numero_orden',
                    'like',
                    $termino
                )
                ->orWhere(
                    'ordenamientos.numero_episodio',
                    'like',
                    $termino
                )
                ->orWhere(
                    'ordenamientos.numero_documento',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.numero_paciente',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.numero_documento',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.primer_nombre',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.segundo_nombre',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.apellido',
                    'like',
                    $termino
                )
                ->orWhere(
                    'pacientes.apellido_soltero',
                    'like',
                    $termino
                )
                ->orWhereExists(function ($subconsulta) use ($termino) {
                    $subconsulta->selectRaw('1')
                        ->from('prestacions')
                        ->whereColumn(
                            'prestacions.ordenamiento_id',
                            'ordenamientos.id'
                        )
                        ->where(function ($prestacion) use ($termino) {
                            $prestacion
                                ->where(
                                    'prestacions.cups',
                                    'like',
                                    $termino
                                )
                                ->orWhere(
                                    'prestacions.descripcion',
                                    'like',
                                    $termino
                                );
                        });
                });
            });
        }

        $ordenes = $consulta
            ->select([
                'ordenamientos.id',
                'ordenamientos.numero_orden',
                'ordenamientos.numero_episodio',
                'ordenamientos.numero_documento',
                'ordenamientos.fecha_solicitud',
                'ordenamientos.hora_solicitud',
                'ordenamientos.sede_solicitante',
                'ordenamientos.prioridad',
                'ordenamientos.especialidad_solicitante',
                'ordenamientos.profesional_solicitante',
                'ordenamientos.codigo_diagnostico',
                'ordenamientos.documento_prof_solicitante',

                'pacientes.numero_paciente',
                'pacientes.tipo_documento',
                'pacientes.numero_documento as documento_paciente',
                'pacientes.primer_nombre',
                'pacientes.segundo_nombre',
                'pacientes.apellido',
                'pacientes.apellido_soltero',
            ])
            ->selectSub(
                DB::table('prestacions')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'prestacions.ordenamiento_id',
                        'ordenamientos.id'
                    ),
                'total_prestaciones'
            )
            ->orderByRaw("
                STR_TO_DATE(
                    ordenamientos.fecha_solicitud,
                    '%d.%m.%Y'
                ) DESC
            ")
            ->orderByDesc('ordenamientos.id')
            ->paginate(25)
            ->withQueryString();

        return response()->json($ordenes);
    }

    /**
     * Consulta base reutilizable para órdenes y estadísticas.
     */
    private function consultaOrdenes(array $filtros)
    {
        $consulta = DB::table('ordenamientos')
            ->join(
                'pacientes',
                'pacientes.id',
                '=',
                'ordenamientos.paciente_id'
            );

        /*
         * Las fechas se almacenan como dd.mm.yyyy.
         * STR_TO_DATE permite compararlas correctamente.
         */
        if (!empty($filtros['desde'])) {
            $consulta->whereRaw(
                "STR_TO_DATE(
                    ordenamientos.fecha_solicitud,
                    '%d.%m.%Y'
                ) >= ?",
                [$filtros['desde']]
            );
        }

        if (!empty($filtros['hasta'])) {
            $consulta->whereRaw(
                "STR_TO_DATE(
                    ordenamientos.fecha_solicitud,
                    '%d.%m.%Y'
                ) <= ?",
                [$filtros['hasta']]
            );
        }

        if (!empty($filtros['sede_solicitante'])) {
            $consulta->where(
                'ordenamientos.sede_solicitante',
                $filtros['sede_solicitante']
            );
        }

        if (!empty($filtros['prioridad'])) {
            $consulta->where(
                'ordenamientos.prioridad',
                $filtros['prioridad']
            );
        }

        if (!empty($filtros['especialidad'])) {
            $consulta->where(
                'ordenamientos.especialidad_solicitante',
                $filtros['especialidad']
            );
        }

        /*
         * Los filtros de prestaciones usan EXISTS.
         * Así, una orden con varias prestaciones aparece
         * una sola vez en el listado.
         */
        $consulta->whereExists(function ($subconsulta) use ($filtros) {
            $subconsulta->selectRaw('1')
                ->from('prestacions')
                ->whereColumn(
                    'prestacions.ordenamiento_id',
                    'ordenamientos.id'
                );

            if (!empty($filtros['sede_ejecucion'])) {
                $subconsulta->where(
                    'prestacions.sede_ejecucion',
                    $filtros['sede_ejecucion']
                );
            }

            if (!empty($filtros['estado'])) {
                $subconsulta->where(
                    'prestacions.estado',
                    $filtros['estado']
                );
            }

            if (!empty($filtros['especialidad_ejecutora'])) {
                $subconsulta->where(
                    'prestacions.especialidad_ejecutora',
                    $filtros['especialidad_ejecutora']
                );
            }
        });

        return $consulta;
    }

    /**
     * Validar y normalizar los filtros recibidos.
     */
    private function validarFiltros(Request $request): array
    {
        $filtros = $request->validate([
            'desde' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'hasta' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:desde',
            ],

            'sede_solicitante' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sede_ejecucion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'prioridad' => [
                'nullable',
                'string',
                'max:100',
            ],

            'especialidad' => [
                'nullable',
                'string',
                'max:150',
            ],

            'especialidad_ejecutora' => [
                'nullable',
                'string',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'string',
                'max:100',
            ],

            'buscar' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        if (
            !empty($filtros['desde']) &&
            !empty($filtros['hasta']) &&
            $filtros['desde'] > $filtros['hasta']
        ) {
            throw ValidationException::withMessages([
                'hasta' =>
                    'La fecha final no puede ser anterior a la inicial.',
            ]);
        }

        return $filtros;
    }

    /**
     * Obtener un ordenamiento con su paciente y prestaciones.
     */
    public function detalle(int $id)
    {
        $orden = Ordenamiento::with([
            'paciente',

            'prestaciones' => function ($consulta) {
                $consulta->orderBy('id_posicion_orden');
            },
        ])->findOrFail($id);

        return response()->json([
            'orden' => [
                'id' => $orden->id,
                'numero_orden' => $orden->numero_orden,
                'numero_episodio' => $orden->numero_episodio,
                'numero_documento' => $orden->numero_documento,
                'fecha_solicitud' => $orden->fecha_solicitud,
                'hora_solicitud' => $orden->hora_solicitud,
                'sede_solicitante' => $orden->sede_solicitante,
                'prioridad' => $orden->prioridad,
                'fuente_orden' => $orden->fuente_orden,
                'especialidad_solicitante' =>
                    $orden->especialidad_solicitante,
                'profesional_solicitante' =>
                    $orden->profesional_solicitante,
                'documento_prof_solicitante' =>
                    $orden->documento_prof_solicitante,
                'codigo_diagnostico' =>
                    $orden->codigo_diagnostico,
                'diagnostico_1' => $orden->diagnostico_1,
                'diagnostico_2' => $orden->diagnostico_2,
                'diagnostico_3' => $orden->diagnostico_3,
            ],

            'paciente' => $orden->paciente,
            'prestaciones' => $orden->prestaciones,
        ]);
    }


    
/**
 * Exportar todas las órdenes filtradas y sus prestaciones a CSV.
 * Procesa las órdenes por lotes para limitar el uso de memoria.
 */
public function exportar(Request $request)
{
    $filtros = $this->validarFiltros($request);

    $base = $this->consultaOrdenes($filtros);

    // Aplicar la misma búsqueda del listado.
    if (!empty($filtros['buscar'])) {
        $termino = '%' . $filtros['buscar'] . '%';

        $base->where(function ($q) use ($termino) {
            $q->where('ordenamientos.numero_orden', 'like', $termino)
                ->orWhere('ordenamientos.numero_episodio', 'like', $termino)
                ->orWhere('ordenamientos.numero_documento', 'like', $termino)
                ->orWhere('pacientes.numero_paciente', 'like', $termino)
                ->orWhere('pacientes.numero_documento', 'like', $termino)
                ->orWhere('pacientes.primer_nombre', 'like', $termino)
                ->orWhere('pacientes.segundo_nombre', 'like', $termino)
                ->orWhere('pacientes.apellido', 'like', $termino)
                ->orWhere('pacientes.apellido_soltero', 'like', $termino)
                ->orWhereExists(function ($sub) use ($termino) {
                    $sub->selectRaw('1')
                        ->from('prestacions')
                        ->whereColumn(
                            'prestacions.ordenamiento_id',
                            'ordenamientos.id'
                        )
                        ->where(function ($p) use ($termino) {
                            $p->where('prestacions.cups', 'like', $termino)
                                ->orWhere(
                                    'prestacions.descripcion',
                                    'like',
                                    $termino
                                );
                        });
                });
        });
    }

    $nombreArchivo = 'ordenes_' . now()->format('Ymd_His') . '.csv';

    return response()->streamDownload(function () use ($base) {
        set_time_limit(0);

        $salida = fopen('php://output', 'w');

        // BOM para facilitar la apertura de tildes y caracteres
        // especiales en Excel.
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, [
            'ID orden',
            'Número orden',
            'Episodio',
            'Fecha solicitud',
            'Hora solicitud',
            'Documento orden',
            'Número paciente',
            'Tipo documento paciente',
            'Documento paciente',
            'Primer nombre',
            'Segundo nombre',
            'Apellido',
            'Apellido soltero',
            'Sede solicitante',
            'Prioridad',
            'Especialidad solicitante',
            'Profesional solicitante',
            'Documento profesional',
            'Código diagnóstico',
            'Fuente orden',
            'Diagnóstico 1',
            'Diagnóstico 2',
            'Diagnóstico 3',
            'ID prestación',
            'Posición prestación',
            'CUPS',
            'Descripción prestación',
            'Tipo orden',
            'Estado prestación',
            'Sede ejecución',
            'Fecha realización',
            'Hora realización',
            'Profesional ejecutor',
            'Especialidad ejecutora',
        ], ';');

        $ultimoId = 0;

        do {
            // Obtener un lote de órdenes únicas, sin paginar por
            // el número de prestaciones.
            $ids = (clone $base)
                ->where('ordenamientos.id', '>', $ultimoId)
                ->select('ordenamientos.id')
                ->distinct()
                ->orderBy('ordenamientos.id')
                ->limit(100)
                ->pluck('ordenamientos.id');

            if ($ids->isEmpty()) {
                break;
            }

            $ultimoId = (int) $ids->last();

            // Una fila por prestación. Las órdenes sin prestaciones
            // también se incluyen, con los campos de prestación vacíos.
            DB::table('ordenamientos')
                ->join(
                    'pacientes',
                    'pacientes.id',
                    '=',
                    'ordenamientos.paciente_id'
                )
                ->leftJoin(
                    'prestacions',
                    'prestacions.ordenamiento_id',
                    '=',
                    'ordenamientos.id'
                )
                ->whereIn('ordenamientos.id', $ids)
                ->select([
                    'ordenamientos.id as orden_id',
                    'ordenamientos.numero_orden',
                    'ordenamientos.numero_episodio',
                    'ordenamientos.fecha_solicitud',
                    'ordenamientos.hora_solicitud',
                    'ordenamientos.numero_documento as documento_orden',
                    'pacientes.numero_paciente',
                    'pacientes.tipo_documento',
                    'pacientes.numero_documento as documento_paciente',
                    'pacientes.primer_nombre',
                    'pacientes.segundo_nombre',
                    'pacientes.apellido',
                    'pacientes.apellido_soltero',
                    'ordenamientos.sede_solicitante',
                    'ordenamientos.prioridad',
                    'ordenamientos.especialidad_solicitante',
                    'ordenamientos.profesional_solicitante',
                    'ordenamientos.documento_prof_solicitante',
                    'ordenamientos.codigo_diagnostico',
                    'ordenamientos.fuente_orden',
                    'ordenamientos.diagnostico_1',
                    'ordenamientos.diagnostico_2',
                    'ordenamientos.diagnostico_3',
                    'prestacions.id as prestacion_id',
                    'prestacions.id_posicion_orden',
                    'prestacions.cups',
                    'prestacions.descripcion',
                    'prestacions.tipo_orden',
                    'prestacions.estado',
                    'prestacions.sede_ejecucion',
                    'prestacions.fecha_realizacion',
                    'prestacions.hora_realizacion',
                    'prestacions.profesional_ejecutor',
                    'prestacions.especialidad_ejecutora',
                ])
                ->orderBy('ordenamientos.id')
                ->orderBy('prestacions.id_posicion_orden')
                ->chunk(1000, function ($filas) use ($salida) {
                    foreach ($filas as $fila) {
                        $valores = (array) $fila;

                        // Evitar que valores importados se interpreten
                        // como fórmulas al abrir el CSV en Excel.
                        foreach ($valores as &$valor) {
                            if (
                                is_string($valor) &&
                                preg_match('/^[\s]*[=+\-@]/u', $valor)
                            ) {
                                $valor = "'" . $valor;
                            }
                        }
                        unset($valor);

                        fputcsv($salida, array_values($valores), ';');
                    }
                });

            // Ayuda a enviar progresivamente la respuesta.
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

        } while ($ids->count() === 100);

        fclose($salida);
    }, $nombreArchivo, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Cache-Control' => 'no-store, no-cache',
    ]);
}


}
