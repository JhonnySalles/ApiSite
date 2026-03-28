<?php

/**
 * Script minimalista para atuar como Mock da API Syncronizer (PostSynchronizer).
 * Ele lê as configurações de resposta de um arquivo JSON temporário para cada teste.
 */

$configPath = __DIR__ . '/../../logs/mock_config.json';
$response = ['statusCode' => 200, 'body' => ['message' => 'Default Success']];

// Se for login, retorna um token fake para destravar o controller
if (str_contains($_SERVER['REQUEST_URI'], '/auth/login')) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['token' => 'mock_jwt_token_123']);
    exit;
}

if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    if ($config) {
        $response = $config;
    }
}

// Log de depuração (opcional, pode ser visto se rodar php -S no console)
// error_log("Request Received: " . $_SERVER['REQUEST_URI']);

http_response_code($response['statusCode']);
header('Content-Type: application/json');
echo json_encode($response['body']);
