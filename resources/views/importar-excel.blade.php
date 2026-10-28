
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Importador inteligente de Excel</title>

    <style>
        body {
            margin: 0;
            padding: 32px 16px;
            background: #10131a;
            color: #f3f4f6;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 1000px;
            margin: auto;
        }

        section {
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #303746;
            border-radius: 14px;
            background: #191e29;
        }

        input, button {
            padding: 12px;
            margin-top: 12px;
            max-width: 100%;
            border-radius: 8px;
        }

        button {
            border: 0;
            background: #6366f1;
            color: white;
            cursor: pointer;
        }

        button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        #btnImportar {
            background: #15803d;
            margin-left: 8px;
        }

        #estado {
            color: #a5b4fc;
            overflow-wrap: anywhere;
        }

        pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            #btnImportar {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
<main>
    <h1>Importador inteligente de Excel</h1>
    <p>Analiza el archivo antes de guardar información en MySQL.</p>

    <section>
        <form id="formulario">
            <label for="archivo">Archivo Excel o CSV</label><br>

            <input
                id="archivo"
                name="archivo"
                type="file"
                accept=".xlsx,.xls,.csv"
                required
            >

            <br>

            <button type="submit" id="btnPrevisualizar">
                Previsualizar archivo
            </button>

            <button type="button" id="btnImportar" disabled>
                Importar a MySQL
            </button>
        </form>

        <p id="estado" role="status">
            Selecciona un archivo para comenzar.
        </p>
    </section>

    <section>
        <h2>Resultado</h2>
        <pre id="resultado">Todavía no se ha analizado ningún archivo.</pre>
    </section>
</main>

<script>
    const formulario = document.getElementById('formulario');
    const archivoInput = document.getElementById('archivo');
    const estado = document.getElementById('estado');
    const resultado = document.getElementById('resultado');
    const btnPrevisualizar = document.getElementById('btnPrevisualizar');
    const btnImportar = document.getElementById('btnImportar');

    let archivoPrevisualizado = null;

    archivoInput.addEventListener('change', () => {
        archivoPrevisualizado = null;
        btnImportar.disabled = true;
        resultado.textContent = 'Debes volver a previsualizar el archivo seleccionado.';
        estado.textContent = 'Archivo seleccionado. Analízalo antes de importar.';
    });

    async function enviarArchivo(url, archivo) {
        const datos = new FormData();
        datos.append('archivo', archivo);

        const respuesta = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector(
                    'meta[name="csrf-token"]'
                ).content
            },
            body: datos
        });

        const json = await respuesta.json();

        if (!respuesta.ok) {
            throw new Error(JSON.stringify(
                json.errors || json,
                null,
                2
            ));
        }

        return json;
    }

    // La ruta /importar se conserva para la previsualización.
    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const archivo = archivoInput.files[0];

        if (!archivo) {
            estado.textContent = 'Selecciona un archivo primero.';
            return;
        }

        btnPrevisualizar.disabled = true;
        btnImportar.disabled = true;
        estado.textContent = 'Analizando archivo...';
        resultado.textContent = '';

        try {
            const json = await enviarArchivo(
                @json(url('/prev')),
                archivo
            );

            archivoPrevisualizado = archivo;

            resultado.textContent = JSON.stringify(json, null, 2);
            estado.textContent = 'Previsualización completada. Revisa el resultado antes de importar.';

            btnImportar.disabled = false;
        } catch (error) {
            archivoPrevisualizado = null;
            estado.textContent = 'No se pudo analizar el archivo.';
            resultado.textContent = error.message;
        } finally {
            btnPrevisualizar.disabled = false;
        }
    });

    // Esta ruta ejecuta el método importar() del controlador.
    btnImportar.addEventListener('click', async () => {
        const archivo = archivoInput.files[0];

        if (!archivo || archivo !== archivoPrevisualizado) {
            estado.textContent = 'Previsualiza el archivo seleccionado antes de importarlo.';
            btnImportar.disabled = true;
            return;
        }

        const confirmado = confirm(
            '¿Confirmas la importación a MySQL? Los registros existentes podrían actualizarse.'
        );

        if (!confirmado) return;

        btnPrevisualizar.disabled = true;
        btnImportar.disabled = true;
        estado.textContent = 'Importando...';

        try {
            const json = await enviarArchivo(
                @json(url('/importar')),
                archivo
            );

            resultado.textContent = JSON.stringify(json, null, 2);
            estado.textContent = 'Proceso de importación finalizado.';
            archivoPrevisualizado = null;
        } catch (error) {
            estado.textContent = 'La importación no se completó.';
            resultado.textContent = error.message;
        } finally {
            btnPrevisualizar.disabled = false;
            btnImportar.disabled = !archivoPrevisualizado;
        }
    });
</script>
</body>
</html>
