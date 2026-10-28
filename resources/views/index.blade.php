
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard analítico | Aprende IA</title>

    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/dashboard.js') }}" defer></script>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <a href="{{ url('/dashboard') }}" class="brand">
                <span class="brand-icon">A</span>
                <span>Aprende<span class="brand-light">IA</span></span>
            </a>

            <p class="menu-label">ANÁLISIS</p>
            <a class="menu-item active" href="{{ url('/dashboard') }}">▦ <span>Dashboard</span></a>
            <a class="menu-item" href="{{ url('/excel') }}">⇧ <span>Importar Excel</span></a>
            <a class="menu-item" href="{{ url('/') }}">✦ <span>Chat con IA</span></a>
            <a class="menu-item" href="{{ url('/rag') }}">⌕ <span>Búsqueda RAG</span></a>

            <div class="sidebar-footer">
                <span class="status-dot"></span>
                Sistema de análisis
                <small>Datos locales</small>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">CENTRO DE INFORMACIÓN</p>
                    <h1>Dashboard analítico</h1>
                    <p class="subtitle">Explora órdenes, pacientes y prestaciones.</p>
                </div>

                <button class="button button-secondary" id="btnActualizar">
                    ↻ Actualizar datos
                </button>

                
            </header>

            <section class="filter-panel">
                <div class="section-heading">
                    <div>
                        <h2>Filtros de consulta</h2>
                        <p>Selecciona el periodo y los criterios de análisis.</p>
                    </div>
                    <button type="button" class="text-button" id="btnLimpiar">
                        Limpiar filtros
                    </button>
                </div>

                <form id="filtros">
                    <div class="filter-grid">
                        <label>Fecha inicial
                            <input type="date" name="desde">
                        </label>

                        <label>Fecha final
                            <input type="date" name="hasta">
                        </label>

                        <label>Sede solicitante
                            <select name="sede_solicitante">
                                <option value="">Todas las sedes</option>
                            </select>
                        </label>

                        <label>Sede de ejecución
                            <select name="sede_ejecucion">
                                <option value="">Todas las sedes</option>
                            </select>
                        </label>

                        <label>Especialidad solicitante
                            <select name="especialidad">
                                <option value="">Todas</option>
                            </select>
                        </label>

                        <label>Prioridad
                            <select name="prioridad">
                                <option value="">Todas</option>
                            </select>
                        </label>

                        <label>Estado de prestación
                            <select name="estado">
                                <option value="">Todos</option>
                            </select>
                        </label>

                        <label>Especialidad ejecutora
                            <select name="especialidad_ejecutora">
                                <option value="">Todas</option>
                            </select>
                        </label>
                    </div>

                    <div class="filter-actions">
                        <span id="estadoConsulta" aria-live="polite">
                            Listo para consultar
                        </span>
                        <button type="submit" class="button button-primary">
                            Aplicar filtros →
                        </button>
                    </div>
                </form>
            </section>

            <section class="kpi-grid">
                <article class="kpi-card">
                    <div class="kpi-top">
                        <span>Pacientes</span>
                        <span class="kpi-icon blue">♙</span>
                    </div>
                    <strong id="totalPacientes">—</strong>
                    <small>Pacientes con órdenes filtradas</small>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top">
                        <span>Ordenamientos</span>
                        <span class="kpi-icon violet">▤</span>
                    </div>
                    <strong id="totalOrdenes">—</strong>
                    <small>Órdenes que cumplen los filtros</small>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top">
                        <span>Prestaciones</span>
                        <span class="kpi-icon green">✚</span>
                    </div>
                    <strong id="totalPrestaciones">—</strong>
                    <small>Prestaciones asociadas a las órdenes</small>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top">
                        <span>Periodo consultado</span>
                        <span class="kpi-icon orange">◷</span>
                    </div>
                    <strong class="period-value" id="periodoConsultado">Todo</strong>
                    <small>Según las fechas seleccionadas</small>
                </article>
            </section>

            <section class="charts-grid">
                <article class="panel chart-panel chart-wide">
                    <div class="panel-heading">
                        <div>
                            <h2>Actividad en el tiempo</h2>
                            <p>Órdenes por fecha de solicitud</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="graficaFechas"></canvas>
                    </div>
                </article>

                <article class="panel chart-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Órdenes por sede</h2>
                            <p>Sede solicitante</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="graficaSedes"></canvas>
                    </div>
                </article>

                <article class="panel chart-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Especialidades</h2>
                            <p>Distribución de solicitudes</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="graficaEspecialidades"></canvas>
                    </div>
                </article>

                <article class="panel chart-panel chart-wide">
                    <div class="panel-heading">
                        <div>
                            <h2>Distribución por prioridad</h2>
                            <p>Órdenes según prioridad registrada</p>
                        </div>
                    </div>
                    <div class="chart-container chart-short">
                        <canvas id="graficaPrioridades"></canvas>
                    </div>
                </article>
            </section>

            <section class="panel table-panel">
                <div class="section-heading table-heading">
                    <div>
                        <h2>Explorador de órdenes</h2>
                        <p>Consulta los registros que cumplen los filtros.</p>
                    </div>
                    <span id="contadorOrdenes" class="record-count">0 registros</span>
                </div>

                <form id="buscadorForm" class="search-form">
                    <input
                        type="search"
                        name="buscar"
                        placeholder="Buscar orden, documento o paciente..."
                        aria-label="Buscar órdenes"
                    >
                    <button class="button button-primary" type="submit">
                        Buscar
                    </button>
                    <button
    type="button"
    id="btnExportarExcel"
    class="button button-secondary"
>
    Exportar a Excel
</button>
                </form>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Fecha</th>
                                <th>Paciente</th>
                                <th>Documento</th>
                                <th>Sede solicitante</th>
                                <th>Especialidad</th>
                                <th>Prioridad</th>
                                <th>Prestaciones</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>

                        <tbody id="tablaOrdenes">
                            <tr>
                                <td colspan="9" class="empty-state">
                                    Aplicando consulta inicial...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <span id="infoPaginacion">Página 1</span>
                    <div>
                        <button
                            type="button"
                            id="paginaAnterior"
                            class="button button-secondary"
                            disabled
                        >
                            ← Anterior
                        </button>

                        <button
                            type="button"
                            id="paginaSiguiente"
                            class="button button-secondary"
                            disabled
                        >
                            Siguiente →
                        </button>
                    </div>
                </div>
            </section>

            <footer class="footer">
                <span>Aprende IA · Dashboard analítico</span>
                <span>Información basada en los registros importados</span>
            </footer>
        </main>
    </div>

    <!-- Modal de detalle del ordenamiento -->
    <div id="modalDetalle" class="modal-detalle" hidden>
        <div class="modal-overlay" id="cerrarModalOverlay"></div>

        <section
            class="modal-contenido"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tituloModalDetalle"
        >
            <header class="modal-cabecera">
                <div>
                    <p class="eyebrow">INFORMACIÓN COMPLETA</p>
                    <h2 id="tituloModalDetalle">Detalle del ordenamiento</h2>
                    <p id="subtituloModalDetalle">Selecciona una orden para consultar sus datos.</p>
                </div>

                <button
                    type="button"
                    id="cerrarModalDetalle"
                    class="modal-cerrar"
                    aria-label="Cerrar detalle"
                >×</button>
            </header>

            <div id="contenidoDetalle" class="modal-cuerpo">
                <p class="empty-state">
                    Selecciona una orden en el explorador para ver sus detalles.
                </p>
            </div>

            <footer class="modal-pie">
                <button
                    type="button"
                    id="btnCerrarDetalle"
                    class="button button-secondary"
                >
                    Cerrar
                </button>
            </footer>
        </section>
    </div>
</body>
</html>
