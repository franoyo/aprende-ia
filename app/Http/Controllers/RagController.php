<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Http;

class RagController extends Controller
{
       public function rag()
    {
        return view('rag');
    }

     public function buscar(Request $request)
    {
        $data = $request->validate([
            'pregunta' => ['required', 'string', 'max:1000'],
        ]);

        $fragmentos = DocumentChunk::all();

        if ($fragmentos->isEmpty()) {
            return response()->json([
                'error' => 'No hay fragmentos disponibles para buscar.',
            ], 404);
        }

        $resultado = Http::timeout(60)
            ->post('http://localhost:11434/api/embed', [
                'model' => 'nomic-embed-text',
                'input' => $data['pregunta'],
            ])
            ->throw()
            ->json();

        $vectorPregunta = $resultado['embeddings'][0] ?? null;

        if (!is_array($vectorPregunta)) {
            return response()->json([
                'error' => 'No se pudo generar el embedding de la pregunta.',
            ], 502);
        }

        $mejorFragmento = null;
        $mejorSimilitud = -1;

        foreach ($fragmentos as $fragmento) {
            $vectorDocumento = $fragmento->embedding;

            if (
                !is_array($vectorDocumento) ||
                count($vectorDocumento) !== count($vectorPregunta)
            ) {
                continue;
            }

            $producto = 0;
            $normaPregunta = 0;
            $normaDocumento = 0;

            foreach ($vectorPregunta as $i => $valor) {
                $documentoValor = $vectorDocumento[$i];

                $producto += $valor * $documentoValor;
                $normaPregunta += $valor ** 2;
                $normaDocumento += $documentoValor ** 2;
            }

            $denominador = sqrt($normaPregunta * $normaDocumento);

            $similitud = $denominador > 0
                ? $producto / $denominador
                : 0;

            if ($similitud > $mejorSimilitud) {
                $mejorSimilitud = $similitud;
                $mejorFragmento = $fragmento;
            }
        }

 if ($mejorSimilitud < 0.70) {
    return response()->json([
        'respuesta' => 'No encontré información suficientemente relacionada en los documentos disponibles para responder con confianza.',
        'documento' => null,
        'similitud' => round($mejorSimilitud, 4),
    ]);
}

$contexto = $mejorFragmento->content;

$respuestaIA = Http::timeout(120)
    ->post('http://localhost:11434/api/chat', [
        'model' => 'qwen2.5:7b',
        'stream' => false,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Responde siempre en español. Utiliza el contexto proporcionado para responder. Si el contexto no contiene información suficiente para responder, dilo claramente. No inventes datos ni afirmes que el contexto dice algo que no dice.',
            ],
            [
                'role' => 'user',
                'content' => "Contexto recuperado:\n{$contexto}\n\nPregunta: {$data['pregunta']}",
            ],
        ],
        'options' => [
            'temperature' => 0.3,
            'num_predict' => 250,
        ],
    ])
    ->throw()
    ->json();

$respuestaTexto = $respuestaIA['message']['content']
    ?? 'No se recibió una respuesta válida del modelo.';

return response()->json([
    'pregunta' => $data['pregunta'],
    'respuesta' => $respuestaTexto,
    'fragmento_encontrado' => $contexto,
    'documento' => $mejorFragmento->source_name,
    'similitud' => round($mejorSimilitud, 4),
]);
    }
}
