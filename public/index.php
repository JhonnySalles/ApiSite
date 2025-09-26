<?php

require __DIR__ . '/../bootstrap/app.php';

use Bramus\Router\Router;

$router = new Router();

$router->setNamespace('\ApiSite\Http\Controllers');

/**
 * Middleware de Autenticação de API
 * Este bloco será executado ANTES de qualquer rota dentro do 'group'.
 * Ele verifica se um token de API estático foi enviado no cabeçalho.
 */
$router->before('POST', '/login', function () {
  $expectedToken = $_ENV['API_TOKEN'];
  $submittedToken = $_SERVER['HTTP_X_API_KEY'] ?? null;

  if ($submittedToken === null || $submittedToken !== $expectedToken) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Acesso não autorizado.']);
    exit();
  }
});

$router->post('/login', 'AuthController@login');

$router->run();