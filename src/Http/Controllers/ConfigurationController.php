<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\LogService;
use ApiSite\Services\ConfigurationService;
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
   * @OA\Property(property="nome", type="string"),
   * @OA\Property(property="ativa", type="boolean"),
   * @OA\Property(property="blogs", type="array", @OA\Items(type="object"), description="Presente apenas para a plataforma 'tumblr'")
   * )
   * )
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida).")
   * )
   */
  public function getAll() {
    $plataforms = $this->configService->getPlatforms();

    $results = $plataforms->map(function ($plataforma) {
      $data = [
        'nome' => $plataforma->nome,
        'ativa' => (bool)$plataforma->ativa, // Garante que seja true/false
      ];

      // 3. Adiciona a chave 'blogs' apenas se a plataforma for 'tumblr'
      if ($plataforma->nome === 'tumblr') {
        // Formata os blogs para remover campos desnecessários como 'plataforma_id'
        $data['blogs'] = $plataforma->blogs->map(function ($blog) {
          return [
            'nome' => $blog->nome,
            'titulo' => $blog->titulo,
            'selecionado' => $blog->selecionado,
          ];
        });
      }

      return $data;
    });

    http_response_code(200);
    echo $results->toJson();
  }

  // GET /api/configuration/platforms/{name}
  /**
   * @OA\Get(
   * path="/api/configuration/platforms/{name}",
   * tags={"Configurações"},
   * summary="Retorna uma plataforma específica.",
   * description="Busca os dados de uma plataforma pelo seu nome.",
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
   * @OA\Schema(type="string")
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(type="object", description="O objeto da plataforma encontrada.")
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma não encontrada.")
   * )
   */
  public function getOne(string $name) {
    try {
      $plataforma = $this->configService->getPlatformByName($name);
      http_response_code(200);
      echo $plataforma->toJson();
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
   * description="Recebe um array de plataformas com seus novos status (ativo/inativo) e os atualiza no banco de dados.",
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
   * required={"id", "ativa"},
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="ativa", type="boolean", example=false)
   * )
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
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function saveAll() {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $this->configService->savePosts($payload);
      http_response_code(200);
      echo json_encode(['message' => 'Plataformas atualizadas com sucesso.']);
    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha na atualização em massa de plataformas.', ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao atualizar as plataformas. ' . $e->getMessage()]);
    }
  }

  // PUT /api/configuration/platforms/{name}
  /**
   * @OA\Put(
   * path="/api/configuration/platforms/{name}",
   * tags={"Configurações"},
   * summary="Atualiza o status de uma única plataforma.",
   * description="Atualiza o status (ativo/inativo) de uma plataforma específica.",
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
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Novo status da plataforma.",
   * @OA\JsonContent(
   * type="object",
   * required={"ativa"},
   * @OA\Property(property="ativa", type="boolean", example=true)
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Plataforma atualizada com sucesso.",
   * @OA\JsonContent(type="object", description="O objeto da plataforma com os dados atualizados.")
   * ),
   * @OA\Response(response=400, description="Payload inválido."),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma não encontrada."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function saveOne(string $name) {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $platform = $this->configService->savePost($name, $payload);
      http_response_code(200);
      echo $platform->toJson();
    } catch (InvalidArgumentException $e) {
      http_response_code(400); // Bad Request ou 404 Not Found
      echo json_encode(['message' => $e->getMessage()]);
    } catch (\Exception $e) {
      LogService::getInstance()->error("Falha ao atualizar a plataforma '$name'.", ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => "Ocorreu um erro ao atualizar a plataforma '$name'."]);
    }
  }
}