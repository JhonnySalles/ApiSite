<?php

require __DIR__ . '/../bootstrap/app.php';

use Bramus\Router\Router;
use ApiSite\Http\Controllers\DocsController;

$router = new Router();

// --- ROTAS DE DOCUMENTAÇÃO ---
$router->get('/docs', DocsController::class . '@ui');
$router->get('/docs/openapi.json', DocsController::class . '@json');

$router->setNamespace('\ApiSite\Http\Controllers');

/**
 * Middleware de Autenticação de API
 * Este bloco será executado ANTES de qualquer rota dentro do 'group'.
 * Ele verifica se um token de API estático foi enviado no cabeçalho.
 */
$router->before('POST', '/login', function () {
  $expectedToken = $_ENV['API_ACCESS_TOKEN'];
  $submittedToken = $_SERVER['HTTP_X_API_KEY'] ?? null;

  if ($submittedToken === null || $submittedToken !== $expectedToken) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Access não autorizado.']);
    exit();
  }
});

// --- ROTAS DA APLICAÇÃO ---
$router->post('/login', 'AuthController@login');
$router->post('/publish', 'PublishController@postsAll');
$router->post('/publish/(\w+)', 'PublishController@post');
$router->get('/history', 'PublishController@history');

// --- ROTAS DE CONFIGURAÇÃO ---
// Todas as rotas dentro deste grupo serão prefixadas com /configuracoes
// e exigirão a chave de API
$router->mount('/configuration', function () use ($router) {
  $router->before('GET|PUT', '/.*', function() {
    $expectedToken = $_ENV['API_ACCESS_TOKEN'];
    $submittedToken = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if ($submittedToken === null || $submittedToken !== $expectedToken) {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Acesso não autorizado.']);
      exit();
    }
  });

  // GET /configuration/platforms
  $router->get('/platforms', 'ConfigurationController@getAll');

  // GET /configuration/platforms/{name}
  $router->get('/platforms/(\w+)', 'ConfigurationController@getOne');

  // PUT /configuration/platforms
  $router->put('/platforms', 'ConfigurationController@saveAll');

  // PUT /configuration/platforms/{name}
  $router->put('/platforms/(\w+)', 'ConfigurationController@saveOne');
});

$router->run();