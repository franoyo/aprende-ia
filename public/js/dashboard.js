document.addEventListener('DOMContentLoaded', () => {

    const btnExportarExcel = document.getElementById('btnExportarExcel');
    const formulario = document.getElementById('filtros');
    const buscadorForm = document.getElementById('buscadorForm');
    const estadoConsulta = document.getElementById('estadoConsulta');

    // Elementos del modal de detalle.
    const modalDetalle = document.getElementById('modalDetalle');
    const contenidoDetalle = document.getElementById('contenidoDetalle');
    const subtituloModalDetalle = document.getElementById('subtituloModalDetalle');
    const tituloModalDetalle = document.getElementById('tituloModalDetalle');

    const elementos = {
        pacientes: document.getElementById('totalPacientes'),
        ordenes: document.getElementById('totalOrdenes'),
        prestaciones: document.getElementById('totalPrestaciones'),
        periodo: document.getElementById('periodoConsultado'),
        tabla: document.getElementById('tablaOrdenes'),
        contador: document.getElementById('contadorOrdenes'),
        paginacion: document.getElementById('infoPaginacion'),
        anterior: document.getElementById('paginaAnterior'),
        siguiente: document.getElementById('paginaSiguiente'),
        actualizar: document.getElementById('btnActualizar'),
        limpiar: document.getElementById('btnLimpiar'),
        buscar: buscadorForm.elements.buscar,
    };

    const rutas = {
        estadisticas: '/estadisticas',
        ordenes: '/ordenes',
        detalleOrden: id => `/ordenes/${encodeURIComponent(id)}`,
    };

    const graficas = {};

    let paginaActual = 1;
    let ultimaPagina = 1;
    let temporizadorBusqueda = null;
    let peticionEstadisticas = null;
    let peticionOrdenes = null;

    const formatoNumero = new Intl.NumberFormat('es-CO');

    // =========================================================
    // FILTROS Y PARÁMETROS
    // =========================================================

    function parametrosFiltros() {
        const parametros = new URLSearchParams(new FormData(formulario));

        for (const [clave, valor] of [...parametros.entries()]) {
            if (!valor.trim()) {
                parametros.delete(clave);
            }
        }

        return parametros;
    }

    function parametrosOrdenes() {
        const parametros = parametrosFiltros();
        const buscar = elementos.buscar.value.trim();

        if (buscar) {
            parametros.set('buscar', buscar);
        }

        parametros.set('page', String(paginaActual));

        return parametros;
    }

    async function consultar(url, parametros, controladorAnterior) {
        if (controladorAnterior) {
            controladorAnterior.abort();
        }

        const controlador = new AbortController();

        const respuesta = await fetch(
            `${url}?${parametros.toString()}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controlador.signal,
            }
        );

        if (!respuesta.ok) {
            const error = new Error(
                `Error HTTP ${respuesta.status}`
            );

            error.status = respuesta.status;
            throw error;
        }

        return {
            datos: await respuesta.json(),
            controlador,
        };
    }

    function actualizarEstado(mensaje, error = false) {
        estadoConsulta.textContent = mensaje;
        estadoConsulta.classList.toggle('error-message', error);
    }

    // =========================================================
    // GRÁFICAS
    // =========================================================

    function destruirGrafica(id) {
        if (graficas[id]) {
            graficas[id].destroy();
            delete graficas[id];
        }
    }

    function crearGrafica(id, tipo, etiquetas, valores, opciones = {}) {
        const canvas = document.getElementById(id);

        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        destruirGrafica(id);

        const horizontal = opciones.horizontal === true;
        const color = opciones.color || '#8098ff';

        graficas[id] = new Chart(canvas, {
            type: tipo,

            data: {
                labels: etiquetas,

                datasets: [{
                    label: opciones.etiqueta || 'Órdenes',
                    data: valores,

                    backgroundColor: tipo === 'line'
                        ? 'rgba(102, 133, 255, 0.16)'
                        : color,

                    borderColor: color,
                    borderWidth: tipo === 'line' ? 2 : 0,
                    borderRadius: tipo === 'bar' ? 5 : 0,
                    fill: tipo === 'line',
                    tension: 0.3,
                    pointRadius: tipo === 'line' ? 2 : 0,
                }],
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',

                interaction: {
                    intersect: false,
                    mode: 'index',
                },

                plugins: {
                    legend: {
                        display: false,
                    },

                    tooltip: {
                        backgroundColor: '#10192a',
                        titleColor: '#edf2fc',
                        bodyColor: '#cbd5e1',
                        borderColor: '#334155',
                        borderWidth: 1,
                    },
                },

                scales: tipo === 'doughnut' ? {} : {
                    x: {
                        grid: {
                            color: '#243149',
                            display: !horizontal,
                        },

                        ticks: {
                            color: '#91a0b9',
                            maxRotation: 35,
                            autoSkip: true,
                        },

                        border: {
                            display: false,
                        },
                    },

                    y: {
                        beginAtZero: true,

                        grid: {
                            color: '#243149',
                            display: horizontal,
                        },

                        ticks: {
                            color: '#91a0b9',
                            precision: 0,
                        },

                        border: {
                            display: false,
                        },
                    },
                },
            },
        });
    }

    function llenarSelect(nombre, registros, valorActual = '') {
        const select = formulario.elements[nombre];

        if (!select) {
            return;
        }

        const seleccionAnterior = valorActual || select.value;
        const opcionesAnteriores = new Map();

        for (const opcion of select.options) {
            if (opcion.value) {
                opcionesAnteriores.set(
                    opcion.value,
                    opcion.textContent
                );
            }
        }

        for (const registro of registros || []) {
            if (registro.etiqueta) {
                opcionesAnteriores.set(
                    String(registro.etiqueta),
                    String(registro.etiqueta)
                );
            }
        }

        const primeraOpcion = select.options[0];

        select.replaceChildren(primeraOpcion);

        for (const [valor, etiqueta] of opcionesAnteriores) {
            if (
                valor === 'Sin sede' ||
                valor === 'Sin especialidad' ||
                valor === 'Sin prioridad'
            ) {
                continue;
            }

            const opcion = document.createElement('option');

            opcion.value = valor;
            opcion.textContent = etiqueta;

            select.appendChild(opcion);
        }

        select.value = seleccionAnterior;
    }

    function actualizarPeriodo() {
        const desde = formulario.elements.desde.value;
        const hasta = formulario.elements.hasta.value;

        if (!desde && !hasta) {
            elementos.periodo.textContent = 'Todo';
        } else if (desde && hasta) {
            elementos.periodo.textContent = `${desde} — ${hasta}`;
        } else {
            elementos.periodo.textContent = desde || hasta;
        }
    }

    // =========================================================
    // CARGAR ESTADÍSTICAS
    // =========================================================

    async function cargarEstadisticas() {
        const parametros = parametrosFiltros();

        actualizarEstado('Consultando estadísticas…');
        formulario.classList.add('is-loading');

        try {
            const resultado = await consultar(
                rutas.estadisticas,
                parametros,
                peticionEstadisticas
            );

            peticionEstadisticas = resultado.controlador;

            const datos = resultado.datos;
            const tarjetas = datos.tarjetas || {};
            const graficasDatos = datos.graficas || {};

            elementos.pacientes.textContent =
                formatoNumero.format(tarjetas.pacientes ?? 0);

            elementos.ordenes.textContent =
                formatoNumero.format(tarjetas.ordenamientos ?? 0);

            elementos.prestaciones.textContent =
                formatoNumero.format(tarjetas.prestaciones ?? 0);

            actualizarPeriodo();

            const fechas = graficasDatos.por_fecha || [];

            crearGrafica(
                'graficaFechas',
                'line',
                fechas.map(item => item.fecha),
                fechas.map(item => Number(item.total)),
                { color: '#8199ff' }
            );

            const sedes = graficasDatos.por_sede_solicitante || [];

            crearGrafica(
                'graficaSedes',
                'bar',
                sedes.map(item => item.etiqueta),
                sedes.map(item => Number(item.total)),
                {
                    horizontal: true,
                    color: '#34d399',
                }
            );

            const especialidades =
                graficasDatos.por_especialidad || [];

            crearGrafica(
                'graficaEspecialidades',
                'bar',
                especialidades.map(item => item.etiqueta),
                especialidades.map(item => Number(item.total)),
                {
                    horizontal: true,
                    color: '#a78bfa',
                }
            );

            const prioridades = graficasDatos.por_prioridad || [];

            crearGrafica(
                'graficaPrioridades',
                'bar',
                prioridades.map(item => item.etiqueta),
                prioridades.map(item => Number(item.total)),
                { color: '#fbbf24' }
            );

            llenarSelect('sede_solicitante', sedes);
            llenarSelect('especialidad', especialidades);
            llenarSelect('prioridad', prioridades);

            actualizarEstado('Estadísticas actualizadas');

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(
                'Error al consultar estadísticas:',
                error
            );

            actualizarEstado(
                'No fue posible cargar las estadísticas. Revisa el controlador.',
                true
            );

        } finally {
            formulario.classList.remove('is-loading');
        }
    }

    // =========================================================
    // UTILIDADES HTML
    // =========================================================

    function escaparHTML(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[caracter]);
    }

    function mostrarVacio(mensaje) {
        elementos.tabla.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">
                    ${escaparHTML(mensaje)}
                </td>
            </tr>
        `;
    }

    // =========================================================
    // MODAL DE DETALLE
    // =========================================================

    function abrirModalDetalle() {
        if (!modalDetalle) {
            console.error(
                'No se encontró #modalDetalle en index.blade.php.'
            );

            return;
        }

        modalDetalle.hidden = false;
        document.body.classList.add('modal-abierto');
    }

    function cerrarModalDetalle() {
        if (modalDetalle) {
            modalDetalle.hidden = true;
            document.body.classList.remove('modal-abierto');
        }
    }

    function campoDetalle(etiqueta, valor) {
        return `
            <div class="detalle-campo">
                <span>${escaparHTML(etiqueta)}</span>
                <strong>${escaparHTML(valor || '—')}</strong>
            </div>
        `;
    }

    function seccionDetalle(titulo, contenido) {
        return `
            <section class="detalle-seccion">
                <h3>${escaparHTML(titulo)}</h3>
                <div class="detalle-grid">
                    ${contenido}
                </div>
            </section>
        `;
    }

    function renderizarDetalle(respuesta) {
        const orden =
            respuesta.ordenamiento ||
            respuesta.orden ||
            respuesta.data?.ordenamiento ||
            respuesta.data?.orden ||
            respuesta.data ||
            respuesta;

        const paciente =
            respuesta.paciente ||
            orden.paciente ||
            respuesta.data?.paciente ||
            {};

        const prestaciones =
            respuesta.prestaciones ||
            orden.prestaciones ||
            respuesta.data?.prestaciones ||
            [];

        const nombrePaciente = [
            paciente.primer_nombre ?? orden.primer_nombre,
            paciente.segundo_nombre ?? orden.segundo_nombre,
            paciente.apellido ?? orden.apellido,
            paciente.apellido_soltero ?? orden.apellido_soltero,
        ].filter(Boolean).join(' ');

        if (tituloModalDetalle) {
            tituloModalDetalle.textContent =
                `Ordenamiento ${orden.numero_orden || ''}`.trim();
        }

        if (subtituloModalDetalle) {
            subtituloModalDetalle.textContent =
                nombrePaciente || 'Detalle completo del ordenamiento';
        }

        // Datos del ordenamiento.
        const datosOrden = [
            campoDetalle('Número de orden', orden.numero_orden),
            campoDetalle('Número de episodio', orden.numero_episodio),
            campoDetalle('Fecha de solicitud', orden.fecha_solicitud),
            campoDetalle('Hora de solicitud', orden.hora_solicitud),
            campoDetalle('Fuente de orden', orden.fuente_orden),
            campoDetalle('Prioridad', orden.prioridad),
            campoDetalle('Sede solicitante', orden.sede_solicitante),
            campoDetalle(
                'Especialidad solicitante',
                orden.especialidad_solicitante
            ),
            campoDetalle(
                'Profesional solicitante',
                orden.profesional_solicitante
            ),
            campoDetalle(
                'Documento profesional',
                orden.documento_prof_solicitante
            ),
            campoDetalle('Diagnóstico 1', orden.diagnostico_1),
            campoDetalle('Diagnóstico 2', orden.diagnostico_2),
            campoDetalle('Diagnóstico 3', orden.diagnostico_3),
            campoDetalle(
                'Código diagnóstico',
                orden.codigo_diagnostico
            ),
        ].join('');

        // Datos del paciente.
        const datosPaciente = [
            campoDetalle('Nombre completo', nombrePaciente),
            campoDetalle(
                'Número de paciente',
                paciente.numero_paciente ?? orden.numero_paciente
            ),
            campoDetalle(
                'Tipo de documento',
                paciente.tipo_documento ?? orden.tipo_documento
            ),
            campoDetalle(
                'Número de documento',
                paciente.numero_documento ??
                    orden.documento_paciente ??
                    orden.numero_documento
            ),
            campoDetalle(
                'Sexo',
                paciente.sexo ?? orden.sexo
            ),
            campoDetalle(
                'Edad',
                [
                    paciente.edad ?? orden.edad,
                    paciente.unidad_edad ?? orden.unidad_edad,
                ].filter(Boolean).join(' ')
            ),
        ].join('');

        // Prestaciones asociadas a la orden.
        const tablaPrestaciones = `
            <section class="detalle-seccion">
                <h3>Prestaciones (${prestaciones.length})</h3>

                <div class="detalle-tabla-scroll">
                    <table class="detalle-tabla">
                        <thead>
                            <tr>
                                <th>Posición</th>
                                <th>CUPS</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Sede ejecución</th>
                                <th>Fecha realización</th>
                                <th>Hora</th>
                                <th>Profesional ejecutor</th>
                                <th>Especialidad ejecutora</th>
                            </tr>
                        </thead>

                        <tbody>
                            ${
                                prestaciones.length
                                    ? prestaciones.map(item => `
                                        <tr>
                                            <td>${escaparHTML(item.id_posicion_orden)}</td>
                                            <td>${escaparHTML(item.cups)}</td>
                                            <td>${escaparHTML(item.descripcion)}</td>
                                            <td>${escaparHTML(item.tipo_orden)}</td>
                                            <td>${escaparHTML(item.estado)}</td>
                                            <td>${escaparHTML(item.sede_ejecucion)}</td>
                                            <td>${escaparHTML(item.fecha_realizacion)}</td>
                                            <td>${escaparHTML(item.hora_realizacion)}</td>
                                            <td>${escaparHTML(item.profesional_ejecutor)}</td>
                                            <td>${escaparHTML(item.especialidad_ejecutora)}</td>
                                        </tr>
                                    `).join('')
                                    : '<tr><td colspan="10">No hay prestaciones asociadas.</td></tr>'
                            }
                        </tbody>
                    </table>
                </div>
            </section>
        `;

        if (contenidoDetalle) {
            contenidoDetalle.innerHTML =
                seccionDetalle(
                    'Datos del ordenamiento',
                    datosOrden
                ) +
                seccionDetalle(
                    'Datos del paciente',
                    datosPaciente
                ) +
                tablaPrestaciones;
        }
    }

    // =========================================================
    // CONSULTAR DETALLE DE UNA ORDEN
    // =========================================================

    async function cargarDetalleOrden(id) {
        if (!id) {
            actualizarEstado(
                'La orden no contiene un identificador para consultar el detalle.',
                true
            );

            return;
        }

        abrirModalDetalle();

        if (contenidoDetalle) {
            contenidoDetalle.innerHTML = `
                <p class="empty-state">
                    Cargando detalle del ordenamiento…
                </p>
            `;
        }

        if (subtituloModalDetalle) {
            subtituloModalDetalle.textContent =
                'Consultando información…';
        }

        try {
            const respuesta = await fetch(
                rutas.detalleOrden(id),
                {
                    method: 'GET',

                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (!respuesta.ok) {
                throw new Error(
                    `Error HTTP ${respuesta.status} al consultar el detalle`
                );
            }

            const datos = await respuesta.json();

            renderizarDetalle(datos);

        } catch (error) {
            console.error(
                'Error al consultar el detalle de la orden:',
                error
            );

            if (contenidoDetalle) {
                contenidoDetalle.innerHTML = `
                    <p class="empty-state">
                        No fue posible cargar el detalle.
                        Revisa la ruta GET /ordenes/{id}
                        y la consola de Laravel.
                    </p>
                `;
            }

            if (subtituloModalDetalle) {
                subtituloModalDetalle.textContent =
                    'Ocurrió un error al consultar la orden.';
            }
        }
    }

    // =========================================================
    // CARGAR ÓRDENES Y MOSTRAR BOTÓN DE DETALLE
    // =========================================================

    async function cargarOrdenes() {
        actualizarEstado('Consultando órdenes…');
        elementos.tabla.classList.add('is-loading');

        try {
            const resultado = await consultar(
                rutas.ordenes,
                parametrosOrdenes(),
                peticionOrdenes
            );

            peticionOrdenes = resultado.controlador;

            const datos = resultado.datos;
            const registros = datos.data || [];

            elementos.contador.textContent =
                `${formatoNumero.format(datos.total ?? registros.length)} registros`;

            paginaActual = Number(datos.current_page || 1);
            ultimaPagina = Number(datos.last_page || 1);

            elementos.paginacion.textContent =
                `Página ${paginaActual} de ${ultimaPagina}`;

            elementos.anterior.disabled = paginaActual <= 1;

            elementos.siguiente.disabled =
                paginaActual >= ultimaPagina;

            if (registros.length === 0) {
                mostrarVacio(
                    'No se encontraron órdenes para estos filtros.'
                );

                return;
            }

            elementos.tabla.innerHTML = registros.map(orden => {
                const paciente = [
                    orden.primer_nombre,
                    orden.segundo_nombre,
                    orden.apellido,
                    orden.apellido_soltero,
                ].filter(Boolean).join(' ');

                const documento =
                    orden.documento_paciente ||
                    orden.numero_documento ||
                    '—';

                return `
                    <tr>
                        <td>
                            ${escaparHTML(orden.numero_orden)}
                        </td>

                        <td>
                            ${escaparHTML(orden.fecha_solicitud || '—')}
                        </td>

                        <td>
                            ${escaparHTML(
                                paciente || orden.numero_paciente || '—'
                            )}
                        </td>

                        <td>
                            ${escaparHTML(documento)}
                        </td>

                        <td>
                            ${escaparHTML(
                                orden.sede_solicitante || '—'
                            )}
                        </td>

                        <td>
                            ${escaparHTML(
                                orden.especialidad_solicitante || '—'
                            )}
                        </td>

                        <td>
                            ${escaparHTML(orden.prioridad || '—')}
                        </td>

                        <td>
                            ${formatoNumero.format(
                                Number(orden.total_prestaciones || 0)
                            )}
                        </td>

                        <td>
                            <button
                                type="button"
                                class="btn-ver-detalle"
                                data-id="${escaparHTML(orden.id)}">
                                Ver detalle
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            actualizarEstado('Órdenes actualizadas');

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(
                'Error al consultar órdenes:',
                error
            );

            mostrarVacio(
                'Error al cargar las órdenes. Revisa la consola y Laravel.'
            );

            actualizarEstado(
                'No fue posible cargar las órdenes',
                true
            );

        } finally {
            elementos.tabla.classList.remove('is-loading');
        }
    }

    // =========================================================
    // EVENTOS DEL BOTÓN DE DETALLE
    // =========================================================

    // Delegación de eventos: funciona después de buscar o paginar.
    elementos.tabla.addEventListener('click', event => {
        const boton = event.target.closest('.btn-ver-detalle');

        if (!boton) {
            return;
        }

        cargarDetalleOrden(boton.dataset.id);
    });

    // Cerrar modal.
    const cerrarModal = document.getElementById(
        'cerrarModalDetalle'
    );

    const cerrarModalBoton = document.getElementById(
        'btnCerrarDetalle'
    );

    const cerrarModalOverlay = document.getElementById(
        'cerrarModalOverlay'
    );

    cerrarModal?.addEventListener(
        'click',
        cerrarModalDetalle
    );

    cerrarModalBoton?.addEventListener(
        'click',
        cerrarModalDetalle
    );

    cerrarModalOverlay?.addEventListener(
        'click',
        cerrarModalDetalle
    );

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            cerrarModalDetalle();
        }
    });

    // =========================================================
    // ACTUALIZAR TODO
    // =========================================================

    async function actualizarTodo() {
        await Promise.all([
            cargarEstadisticas(),
            cargarOrdenes(),
        ]);
    }

    // =========================================================
    // EVENTOS DE FILTROS Y BÚSQUEDA
    // =========================================================

    formulario.addEventListener('submit', event => {
        event.preventDefault();

        paginaActual = 1;

        actualizarTodo();
    });

    buscadorForm.addEventListener('submit', event => {
        event.preventDefault();

        paginaActual = 1;

        cargarOrdenes();
    });

    // Página anterior.
    elementos.anterior.addEventListener('click', () => {
        if (paginaActual > 1) {
            paginaActual--;

            cargarOrdenes();
        }
    });

    // Página siguiente.
    elementos.siguiente.addEventListener('click', () => {
        if (paginaActual < ultimaPagina) {
            paginaActual++;

            cargarOrdenes();
        }
    });

    // Actualizar dashboard.
    elementos.actualizar.addEventListener(
        'click',
        actualizarTodo
    );

    // Limpiar filtros.
    elementos.limpiar.addEventListener('click', () => {
        formulario.reset();

        elementos.buscar.value = '';

        paginaActual = 1;

        actualizarTodo();
    });

    // Búsqueda automática con retraso.
    elementos.buscar.addEventListener('input', () => {
        clearTimeout(temporizadorBusqueda);

        temporizadorBusqueda = setTimeout(() => {
            paginaActual = 1;

            cargarOrdenes();
        }, 350);
    });
btnExportarExcel?.addEventListener('click', () => {
    const parametros = parametrosFiltros();
    const buscar = elementos.buscar.value.trim();

    if (buscar) {
        parametros.set('buscar', buscar);
    }

    const query = parametros.toString();

    window.location.href = query
        ? `/ordenes/exportar?${query}`
        : '/ordenes/exportar';
});
    // Carga inicial.
    actualizarTodo();
});