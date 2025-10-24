<?php

require __DIR__ . '/../bootstrap/app.php';

use Bramus\Router\Router;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$router = new Router();

$router->options('/.*', function () {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');
  header('Access-Control-Max-Age: 86400');
  exit(0);
});

$router->setNamespace('\ApiSite\Http\Controllers');

// --- ROTAS DE DOCUMENTAÇÃO ---
$router->get('/docs', 'DocsController@ui');
$router->get('/docs/api-spec', 'DocsController@json');
$router->get('/health', 'HealthController@check');

$router->get('/swagger-ui.html|/api-docs|/documentacao', function () {
  http_response_code(302);
  header('Location: /docs');
  exit();
});

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
    echo json_encode(['message' => 'Acesso não autorizado.']);
    exit();
  }
});
$router->post('/login', 'AuthController@login');
$router->post('/refresh', 'AuthController@refresh');

// --- GRUPO DE ROTAS PROTEGIDAS DA API ---
$router->mount('/api', function () use ($router) {

  // Middleware de segurança para TODO o grupo /api
  $router->before('GET|POST|PUT|DELETE', '/.*', function () {
    // 1. Validação do X-API-KEY (para todas as rotas protegidas)
    $expectedStaticToken = $_ENV['API_ACCESS_TOKEN'];
    $submittedStaticToken = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if ($submittedStaticToken === null || $submittedStaticToken !== $expectedStaticToken) {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Acesso não autorizado (API Key inválida).']);
      exit();
    }

    // 2. Validação do Token JWT
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if ($authHeader === null || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Token de acesso não fornecido ou mal formatado.']);
      exit();
    }

    $jwt = $matches[1];
    try {
      $secretKey = $_ENV['JWT_SECRET'];
      JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (\Exception $e) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Token de acesso inválido ou expirado.']);
      exit();
    }
  });

  // Endpoints de Publicação (ex: /api/publish)
  $router->post('/publish', 'PublishController@postsAll');
  $router->post('/publish/(\w+)', 'PublishController@post');

  // Endpoint de Histórico (ex: /api/history)
  $router->get('/history', 'HistoryController@history');
  $router->delete('/history/(\d+)', 'HistoryController@delete');

  // Endpoint de Tags (ex: /api/tags)
  $router->get('/tags', 'TagController@tags');

  // Endpoint de Plataforma (ex: /api/platforms/tumblr/blogs)
  $router->get('/platforms/tumblr/blogs', 'PlatformController@getTumblrBlogs');

  // Endpoints de Configuração (ex: /api/configuration/platforms)
  $router->mount('/configuration', function () use ($router) {
    $router->get('/platforms', 'ConfigurationController@getAll');
    $router->get('/platforms/(\w+)', 'ConfigurationController@getOne');
    $router->put('/platforms', 'ConfigurationController@saveAll');
    $router->put('/platforms/(\w+)', 'ConfigurationController@saveOne');
  });

  // Endpoints de Rascunhos (ex: /api/draft)
  $router->mount('/draft', function () use ($router) {
    $router->post('/', 'DraftController@saveOne');
    $router->post('/saveAll', 'DraftController@saveAll');
    $router->get('/', 'DraftController@getAll');
    $router->get('/(\d+)', 'DraftController@getOne');
    $router->delete('/(\d+)', 'DraftController@delete');
  });
});

$router->run();