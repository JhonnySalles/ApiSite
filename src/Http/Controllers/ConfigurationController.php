<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\ConfigurationService;
use ApiSite\Services\LogService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ConfigurationController {
  private $configService;

  public function __construct() {
    $this->configService = new ConfigurationService();
  }

  // GET /api/configuration/platforms

  /**
   * @OA\Get(
   * path="/api/configuration/platforms",
   * tags={"Configurações"},
   * summary="Retorna a lista de todas as plataformas.",
   * description="Busca todas as plataformas configuradas. Para a plataforma 'tumblr', retorna também a lista de blogs associados.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="Authorization",
   * in="header",
   * required=true,
   * description="Token JWT de autenticação do usuário. (Formato: Bearer token)",
   * @OA\Schema(type="string")
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="nome", type="string", example="tumblr"),
   * @OA\Property(property="ativo", type="boolean", example=true),
   * @OA\Property(
   * property="blogs",
   * type="array",
   * description="Presente apenas para a plataforma 'tumblr'",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=10),
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="titulo", type="string", example="Meu Blog Fantástico"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * ),
   * example={
   * {
   * "id": 1,
   * "nome": "tumblr",
   * "ativo": true,
   * "blogs": {
   * {
   * "id": 10,
   * "nome": "meu-blog",
   * "titulo": "Meu Blog Fantástico",
   * "selecionado": true
   * },
   * {
   * "id": 11,
   * "nome": "blog-secundario",
   * "titulo": "Blog Secundário",
   * "selecionado": false
   * }
   * }
   * },
   * {
   * "id": 2,
   * "nome": "x",
   * "ativo": true
   * }
   * }
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida).")
   * )
   */
  public function getAll() {
    $platforms = $this->configService->getPlatforms();
    $formatted = $this->formatPlatformResponse($platforms);
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($formatted);
  }

  // GET /api/configuration/platforms/{name}

  /**
   * @OA\Get(
   * path="/api/configuration/platforms/{name}",
   * tags={"Configurações"},
   * summary="Retorna uma plataforma específica.",
   * description="Busca os dados de uma plataforma pelo seu nome, incluindo blogs se for o Tumblr.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="Authorization",
   * in="header",
   * required=true,
   * description="Token JWT de autenticação do usuário. (Formato: Bearer token)",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="name",
   * in="path",
   * required=true,
   * description="Nome da plataforma a ser buscada.",
   * @OA\Schema(type="string", example="tumblr")
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="nome", type="string", example="tumblr"),
   * @OA\Property(property="ativo", type="boolean", example=true),
   * @OA\Property(
   * property="blogs",
   * type="array",
   * description="Presente apenas para a plataforma 'tumblr'",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=10),
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="titulo", type="string", example="Meu Blog Fantástico"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma não encontrada.")
   * )
   */
  public function getOne(string $name) {
    try {
      $platform = $this->configService->getPlatformByName($name);
      $formatted = $this->formatPlatformResponse(new Collection([$platform]))->first();
      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode($formatted);
    } catch (InvalidArgumentException $e) {
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);
    }
  }

  // PUT /api/configuration/platforms

  /**
   * @OA\Put(
   * path="/api/configuration/platforms",
   * tags={"Configurações"},
   * summary="Atualiza o status de múltiplas plataformas.",
   * description="Recebe um array de plataformas com seus novos status (ativo/inativo) e, para o Tumblr, as informações de seleção de blogs. O 'nome' da plataforma é usado para identificação.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="Authorization",
   * in="header",
   * required=true,
   * description="Token JWT de autenticação do usuário. (Formato: Bearer token)",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Array de objetos de plataforma para atualização.",
   * @OA\JsonContent(
   * type="array",
   * @OA\Items(
   * type="object",
   * required={"nome", "ativo"},
   * @OA\Property(property="nome", type="string", example="tumblr"),
   * @OA\Property(property="ativo", type="boolean", example=true),
   * @OA\Property(
   * property="blogs",
   * type="array",
   * description="Opcional. Usado apenas para a plataforma 'tumblr' para definir o blog selecionado.",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * ),
   * example={
   * {
   * "nome": "tumblr",
   * "ativo": true,
   * "blogs": {
   * {
   * "nome": "meu-blog",
   * "selecionado": true
   * },
   * {
   * "nome": "blog-secundario",
   * "selecionado": false
   * }
   * }
   * },
   * {
   * "nome": "x",
   * "ativo": false
   * }
   * }
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Plataformas atualizadas com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Plataformas atualizadas com sucesso.")
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function saveAll() {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $this->configService->savePlatforms($payload);
      http_response_code(200);
      echo json_encode(['message' => 'Plataformas atualizadas com sucesso.']);
    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha na atualização em massa de plataformas.', ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao atualizar as plataformas: ' . $e->getMessage()]);
    }
  }

  // PUT /api/configuration/platforms/{name}

  /**
   * @OA\Put(
   * path="/api/configuration/platforms/{name}",
   * tags={"Configurações"},
   * summary="Atualiza o status de uma única plataforma.",
   * description="Atualiza o status (ativo/inativo) de uma plataforma específica e, se for o Tumblr, permite atualizar qual blog está selecionado.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="Authorization",
   * in="header",
   * required=true,
   * description="Token JWT de autenticação do usuário. (Formato: Bearer token)",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="name",
   * in="path",
   * required=true,
   * description="Nome da plataforma a ser atualizada.",
   * @OA\Schema(type="string", example="tumblr")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Novos dados da plataforma.",
   * @OA\JsonContent(
   * type="object",
   * required={"ativo"},
   * @OA\Property(property="ativo", type="boolean", example=true),
   * @OA\Property(
   * property="blogs",
   * type="array",
   * description="Opcional. Usado apenas para a plataforma 'tumblr' para definir o blog selecionado.",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Plataforma atualizada com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * description="O objeto da plataforma com os dados atualizados.",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="nome", type="string", example="tumblr"),
   * @OA\Property(property="ativo", type="boolean", example=true),
   * @OA\Property(
   * property="blogs",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=10),
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="titulo", type="string", example="Meu Blog Fantástico"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * )
   * ),
   * @OA\Response(response=400, description="Payload inválido."),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma não encontrada."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function saveOne(string $name) {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $platform = $this->configService->savePlatform($name, $payload);
      $formatted = $this->formatPlatformResponse(new Collection([$platform]))->first();
      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode($formatted);
    } catch (InvalidArgumentException $e) {
      http_response_code(400);
      echo json_encode(['message' => $e->getMessage()]);
    } catch (\Exception $e) {
      LogService::getInstance()->error("Falha ao atualizar a plataforma '$name'.", ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => "Ocorreu um erro ao atualizar a plataforma '$name'."]);
    }
  }

  /**
   * Helper privado para formatar a saída JSON das plataformas.
   */
  private function formatPlatformResponse(Collection $platforms) {
    return $platforms->map(function ($platform) {
      $data = ['id' => $platform->id, 'nome' => $platform->nome, 'ativo' => (bool)$platform->ativo,];

      if ($platform->nome === 'tumblr' && $platform->blogs->isNotEmpty()) {
        $data['blogs'] = $platform->blogs->map(function ($blog) {
          return ['id' => $blog->id, 'nome' => $blog->nome, 'titulo' => $blog->titulo, 'selecionado' => (bool)$blog->selecionado,];
        })->values();
      }

      return $data;
    });
  }
}