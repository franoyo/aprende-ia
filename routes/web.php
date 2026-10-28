<?php

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Models\DocumentChunk;
use App\Http\Controllers\RagController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Controllers\ImportacionExcelController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('chat');
});


Route::post('/chat-ia', function (Request $request) {
    $data = $request->validate([
        'pregunta' => ['required', 'string', 'max:2000'],
        'conversation_id' => ['nullable', 'integer', 'min:1'],
    ]);

    // Recuperar una conversación existente o crear una nueva.
    if (!empty($data['conversation_id'])) {
        $conversation = Conversation::findOrFail(
            $data['conversation_id']
        );
    } else {
        $conversation = Conversation::create([
            'title' => mb_substr($data['pregunta'], 0, 100),
        ]);
    }

    // Guardar la pregunta del usuario.
    $conversation->messages()->create([
        'role' => 'user',
        'content' => $data['pregunta'],
    ]);

    // Recuperar el historial desde MySQL.
    $messages = [
        [
            'role' => 'system',
            'content' => 'Responde en español de forma clara y natural.',
        ],
    ];

    foreach (
        $conversation->messages()
            ->orderBy('id')
            ->get() as $message
    ) {
        $messages[] = [
            'role' => $message->role,
            'content' => $message->content,
        ];
    }

    // Consultar el modelo local.
    $response = Http::timeout(120)->post(
        'http://localhost:11434/api/chat',
        [
            'model' => 'qwen2.5:7b',
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => 0.3,
                'num_predict' => 200,
            ],
        ]
    );

    $response->throw();

    $answer = $response->json('message.content');

    // Guardar la respuesta de la IA.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => $answer,
    ]);

    return response()->json([
        'conversation_id' => $conversation->id,
        'respuesta' => $answer,
    ]);
});



Route::controller(RagController::class)->group(function(){
    Route::get('/rag', 'rag')->name('rag');
    Route::POST('/buscar', 'buscar')->name('buscar');



});

Route::controller(ImportacionExcelController::class)->group(function(){
    Route::get('/excel', 'index')->name('excel');
    Route::POST('/previsualizar', 'previsualizar')->name('previsualizar');
     Route::POST('/importar', 'importar')->name('importar');
     Route::POST('/prev', 'prev')->name('prev');



});

Route::controller(DashboardController::class)->group(function(){
    Route::get('/dashboard', 'index')->name('dashboard');
    Route::get('/estadisticas', 'estadisticas')->name('estadisticas');
     Route::get('/ordenes', 'ordenes')->name('ordenes');
        Route::get('/ordenes/exportar', 'exportar')->name('ordenes.exportar');
   Route::get('/ordenes/{id}', 'detalle')->name('ordenes.detalle');




});