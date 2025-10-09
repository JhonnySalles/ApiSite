<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\PublishService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class DraftController {
  private $publishService;

  public function __construct() {
    $this->publishService = new PublishService();
  }

  // POST /api/draft
  /**
   * @OA\Post(
   * path="/api/draft",
   * tags={"Rascunhos"},
   * summary="Cria ou atualiza um rascunho.",
   * description="Salva uma nova postagem com o tipo 'RASCUNHO'. Se um 'id' for fornecido no corpo, o rascunho existente será atualizado.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Payload contendo os dados do rascunho a ser criado ou atualizado.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="id", type="integer", description="ID do rascunho para atualização (opcional).", example=1),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="base64", type="string", format="byte", description="Imagem em formato base64 para novo upload."),
   * @OA\Property(property="url", type="string", format="uri", description="URL de uma imagem já existente."),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string", example="tumblr"))
   * )
   * )
   * )
   * ),
   * @OA\Response(
   * response=201,
   * description="Rascunho criado/atualizado com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="base64", type="string", format="byte"),
   * @OA\Property(property="url", type="string", format="uri"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string"))
   * )
   * )
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida).")
   * )
   */
  public function saveOne() {
    try {
      $payload = json_decode(file_get_contents('php://input'), true);
      $draft = $this->publishService->saveDraft($payload);
      http_response_code(201);
      header('Content-Type: application/json');
      echo $draft->toJson();
    } catch (Exception $e) {
      \ApiSite\Services\LogService::getInstance()->error('Falha ao salvar rascunho.', ['error' => $e->getMessage()]);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao salvar o rascunho.']);
    }
  }

  // POST /api/draft/saveAll
  /**
   * @OA\Post(
   * path="/api/draft/saveAll",
   * tags={"Rascunhos"},
   * summary="Cria ou atualiza múltiplos rascunhos em lote.",
   * description="Recebe um array de objetos de rascunho e os salva/atualiza no banco de dados.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Array de payloads de rascunhos a serem criados ou atualizados.",
   * @OA\JsonContent(
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", description="ID do rascunho para atualização (opcional).", example=1),
   * @OA\Property(property="text", type="string", example="Outra ideia..."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="ideia")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="base64", type="string", format="byte"),
   * @OA\Property(property="url", type="string", format="uri"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string"))
   * )
   * )
   * )
   * )
   * ),
   * @OA\Response(
   * response=201,
   * description="Rascunhos salvos com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Rascunhos salvos com sucesso.")
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida).")
   * )
   */
  public function saveAll() {
    try {
      $payload = json_decode(file_get_contents('php://input'), true);
      $this->publishService->saveDrafts($payload);
      http_response_code(201);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Rascunhos salvos com sucesso.']);
    } catch (Exception $e) {
      \ApiSite\Services\LogService::getInstance()->error('Falha ao salvar rascunhos em massa.', ['error' => $e->getMessage()]);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao salvar os rascunhos.']);
    }
  }

  // GET /api/draft
  /**
   * @OA\Get(
   * path="/api/draft",
   * tags={"Rascunhos"},
   * summary="Retorna uma lista paginada de rascunhos.",
   * description="Busca todos as postagens do tipo 'RASCUNHO' que não foram excluídas.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="page",
   * in="query",
   * description="Número da página.",
   * @OA\Schema(type="integer", default=1)
   * ),
   * @OA\Parameter(
   * name="size",
   * in="query",
   * description="Itens por página.",
   * @OA\Schema(type="integer", default=10)
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(
   * type="object",
   * description="Objeto de paginação contendo os rascunhos.",
   * @OA\Property(property="pagina", type="integer", example=1),
   * @OA\Property(property="total", type="integer", example=25),
   * @OA\Property(
   * property="data",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="url", type="string", format="uri"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string"))
   * )
   * )
   * )
   * )
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida).")
   * )
   */
  public function getAll() {
    try {
      $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
      $size = isset($_GET['size']) ? (int)$_GET['size'] : 10;
      $rascunhos = $this->publishService->getDraftsPaginated($page, $size);
      http_response_code(200);
      header('Content-Type: application/json');
      echo $rascunhos->toJson();
    } catch (Exception $e) {
      \ApiSite\Services\LogService::getInstance()->error('Falha ao buscar rascunhos.', ['error' => $e->getMessage()]);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao buscar os rascunhos.']);
    }
  }

  // GET /api/draft/{id}
  /**
   * @OA\Get(
   * path="/api/draft/{id}",
   * tags={"Rascunhos"},
   * summary="Retorna um rascunho específico.",
   * description="Busca um rascunho pelo seu ID.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="id",
   * in="path",
   * required=true,
   * description="ID do rascunho a ser buscado.",
   * @OA\Schema(type="integer")
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="id", type="integer", example=1),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="url", type="string", format="uri", description="URL da imagem no bucket."),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string", example="tumblr"))
   * )
   * )
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Rascunho não encontrado.")
   * )
   */
  public function getOne(int $id) {
    try {
      $draft = $this->publishService->getDraft($id);
      http_response_code(200);
      header('Content-Type: application/json');
      echo $draft->toJson();
    } catch (ModelNotFoundException $e) {
      http_response_code(404);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Rascunho não encontrado.']);
    }
  }

  // DELETE /api/draft/{id}
  /**
   * @OA\Delete(
   * path="/api/draft/{id}",
   * tags={"Rascunhos"},
   * summary="Exclui um rascunho.",
   * description="Realiza a exclusão lógica de um rascunho, alterando sua situação para 'EXCLUIDO'.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="id",
   * in="path",
   * required=true,
   * description="ID do rascunho a ser excluído.",
   * @OA\Schema(type="integer")
   * ),
   * @OA\Response(
   * response=204,
   * description="Rascunho excluído com sucesso."
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Rascunho não encontrado.")
   * )
   */
  public function delete(int $id) {
    try {
      $this->publishService->deleteDraft($id);
      http_response_code(204);
    } catch (ModelNotFoundException $e) {
      http_response_code(404);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Rascunho não encontrado.']);
    }
  }
}