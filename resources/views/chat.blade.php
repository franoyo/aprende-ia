
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mi IA local</title>
</head>
<body>
    <h1>Mi primera IA local</h1>

    <form id="chat-form">
        <label for="pregunta">Escribe tu pregunta:</label>
        <input
            id="pregunta"
            name="pregunta"
            type="text"
            required
            maxlength="2000"
        >
        <button id="enviar" type="submit">Preguntar</button>
    </form>

    <p id="estado"></p>
    <pre id="respuesta"></pre>

    <script>
        const form = document.getElementById('chat-form');
        const pregunta = document.getElementById('pregunta');
        const respuesta = document.getElementById('respuesta');
        const estado = document.getElementById('estado');
        const enviar = document.getElementById('enviar');

        // Identificador persistente de la conversación actual.
        let conversationId = null;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const textoPregunta = pregunta.value.trim();

            if (!textoPregunta) return;

            estado.textContent = 'La IA está pensando...';
            enviar.disabled = true;

            try {
                const res = await fetch('/chat-ia', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                    },
                    body: JSON.stringify({
                        pregunta: textoPregunta,
                        conversation_id: conversationId
                    })
                });

                const data = await res.json();

                if (!res.ok) {
                    throw new Error(
                        data.message || 'Error al consultar la IA'
                    );
                }

                // Conservar el ID que devuelve Laravel.
                conversationId = data.conversation_id;

                respuesta.textContent = data.respuesta;
                estado.textContent = 'Respuesta recibida';

                pregunta.value = '';
                pregunta.focus();

            } catch (error) {
                estado.textContent = error.message;
            } finally {
                enviar.disabled = false;
            }
        });
    </script>
</body>
</html>
