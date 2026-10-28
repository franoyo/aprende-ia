
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi IA Local | RAG</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #0b1120;
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        .app {
            width: 100%;
            max-width: 850px;
            height: min(760px, 90vh);
            min-height: 500px;
            display: flex;
            flex-direction: column;
            background: #111827;
            border: 1px solid #293449;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 70px #0005;
        }

        header {
            padding: 22px 26px;
            border-bottom: 1px solid #293449;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: #2563eb;
            font-size: 23px;
        }

        h1 {
            font-size: 19px;
            margin: 0 0 5px;
        }

        .subtitle {
            font-size: 12px;
            color: #9ca3af;
        }

        .status {
            margin-left: auto;
            color: #86efac;
            font-size: 12px;
            white-space: nowrap;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 26px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .welcome {
            margin: auto;
            max-width: 440px;
            text-align: center;
            color: #9ca3af;
            line-height: 1.6;
        }

        .welcome .emoji {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .welcome h2 {
            color: #f9fafb;
            font-size: 23px;
            margin: 0 0 8px;
        }

        .message {
            max-width: 85%;
            line-height: 1.65;
            padding: 14px 17px;
            border-radius: 16px;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .user {
            align-self: flex-end;
            background: #1d4ed8;
            color: white;
            border-bottom-right-radius: 5px;
        }

        .assistant {
            align-self: flex-start;
            background: #1f2937;
            border: 1px solid #374151;
            border-bottom-left-radius: 5px;
        }

        .sources {
            margin-top: 13px;
            padding-top: 12px;
            border-top: 1px solid #374151;
            font-size: 12px;
            color: #9ca3af;
        }

        .sources strong {
            display: block;
            color: #93c5fd;
            margin-bottom: 5px;
        }

        .composer {
            padding: 18px 22px 12px;
            border-top: 1px solid #293449;
        }

        form {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #0b1120;
            border: 1px solid #374151;
            border-radius: 15px;
            padding: 7px;
        }

        input {
            flex: 1;
            min-width: 0;
            padding: 12px;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 15px;
        }

        input::placeholder {
            color: #6b7280;
        }

        button {
            border: none;
            border-radius: 11px;
            background: #2563eb;
            color: white;
            padding: 12px 18px;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            opacity: .5;
            cursor: wait;
        }

        .hint {
            color: #6b7280;
            text-align: center;
            font-size: 11px;
            margin-top: 10px;
        }

        .error {
            color: #fca5a5;
        }

        @media (max-width: 600px) {
            body {
                padding: 0;
            }

            .app {
                height: 100dvh;
                min-height: 0;
                border-radius: 0;
            }

            header {
                padding: 17px;
            }

            .messages {
                padding: 18px;
            }

            .message {
                max-width: 95%;
            }

            .composer {
                padding: 12px;
            }

            button {
                padding: 12px;
            }

            .status {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
<div class="app">
    <header>
        <div class="logo">✦</div>

        <div>
            <h1>Mi IA Local</h1>
            <div class="subtitle">RAG · Ollama · Qwen 2.5</div>
        </div>

        <div class="status">● Local</div>
    </header>

    <main class="messages" id="mensajes" aria-live="polite">
        <div class="welcome" id="bienvenida">
            <div class="emoji">✦</div>
            <h2>¿Qué quieres consultar?</h2>
            Pregúntame algo sobre la información que tengo disponible.
            Buscaré fragmentos relevantes y los usaré como contexto para responder.
        </div>
    </main>

    <div class="composer">
        <form id="form-rag">
            <input
                type="text"
                id="pregunta"
                name="pregunta"
                placeholder="Escribe tu pregunta..."
                maxlength="1000"
                autocomplete="off"
                required
            >

            <button type="submit" id="boton">Enviar ↑</button>
        </form>

        <div class="hint">
            Las respuestas pueden contener errores. Verifica la fuente cuando sea importante.
        </div>
    </div>
</div>

<script>
    const formulario = document.getElementById('form-rag');
    const campo = document.getElementById('pregunta');
    const boton = document.getElementById('boton');
    const mensajes = document.getElementById('mensajes');
    const bienvenida = document.getElementById('bienvenida');

    function agregarMensaje(texto, tipo) {
        const elemento = document.createElement('div');
        elemento.className = 'message ' + tipo;

        // Usar textContent evita interpretar la respuesta como HTML.
        elemento.textContent = texto;

        mensajes.appendChild(elemento);
        mensajes.scrollTop = mensajes.scrollHeight;

        return elemento;
    }

    formulario.addEventListener('submit', async function (event) {
        event.preventDefault();

        const pregunta = campo.value.trim();

        if (!pregunta || boton.disabled) {
            return;
        }

        bienvenida?.remove();

        agregarMensaje(pregunta, 'user');

        campo.value = '';
        boton.disabled = true;
        boton.textContent = '...';

        const respuestaElemento = agregarMensaje('Buscando información...', 'assistant');

        try {
            const respuesta = await fetch(
                @json(url('/buscar')),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    body: JSON.stringify({ pregunta })
                }
            );

            const datos = await respuesta.json();

            if (!respuesta.ok) {
                throw new Error(
                    datos.message || datos.error || 'No se pudo completar la búsqueda.'
                );
            }

            respuestaElemento.textContent = datos.respuesta || 'No se recibió una respuesta.';

            if (datos.documento) {
                const fuentes = document.createElement('div');
                fuentes.className = 'sources';

                const titulo = document.createElement('strong');
                titulo.textContent = 'Fuente recuperada';

                const detalle = document.createElement('span');
                const similitud = Number(datos.similitud);

                detalle.textContent = datos.documento
                    + (Number.isFinite(similitud)
                        ? ' · Similitud: ' + similitud.toFixed(4)
                        : '');

                fuentes.append(titulo, detalle);
                respuestaElemento.appendChild(fuentes);
            }
        } catch (error) {
            respuestaElemento.textContent = 'Error: ' + error.message;
            respuestaElemento.classList.add('error');
        } finally {
            boton.disabled = false;
            boton.textContent = 'Enviar ↑';
            campo.focus();
            mensajes.scrollTop = mensajes.scrollHeight;
        }
    });
</script>
</body>
</html>
