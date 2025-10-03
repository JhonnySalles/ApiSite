<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\DraftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DraftController {
  private $draftService;

  public function __construct() {
    $this->draftService = new DraftService();
  }

  // POST /api/draft
  /**
   * @OA\Post(
   * path="/api/draft",
   * tags={"Rascunhos"},
   * summary="Cria um novo rascunho.",
   * description="Salva uma nova postagem com o tipo 'RASCUNHO' no banco de dados.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Payload contendo os dados do rascunho a ser criado.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="text", type="string", example="Ideia para um novo post..."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="rascunho"))
   * )
   * ),
   * @OA\Response(
   * response=201,
   * description="Rascunho criado com sucesso.",
   * @OA\JsonContent(type="object", description="O objeto do rascunho criado.")
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida).")
   * )
   */
  public function saveOne() {
    $payload = json_decode(file_get_contents('php://input'), true);
    $rascunho = $this->draftService->criarRascunho($payload);
    http_response_code(201); // Created
    echo $rascunho->toJson();
  }

  // POST /api/draft/saveAll
  /**
   * @OA\Post(
   * path="/api/draft/saveAll",
   * tags={"Rascunhos"},
   * summary="Cria múltiplos rascunhos em lote.",
   * description="Recebe um array de objetos de rascunho e os salva no banco de dados.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Array de payloads de rascunhos a serem criados.",
   * @OA\JsonContent(
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="text", type="string", example="Outra ideia..."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="ideia"))
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
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida).")
   * )
   */
  public function saveAll() {
    $payload = json_decode(file_get_contents('php://input'), true);
    $this->draftService->criarRascunhosEmMassa($payload);
    http_response_code(201);
    echo json_encode(['message' => 'Rascunhos salvos com sucesso.']);
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
   * @OA\JsonContent(type="object", description="Objeto de paginação contendo os rascunhos.")
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida).")
   * )
   */
  public function getAll() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 10;
    $rascunhos = $this->draftService->getRascunhosPaginado($page, $size);
    http_response_code(200);
    echo $rascunhos->toJson();
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
   * @OA\JsonContent(type="object", description="O objeto do rascunho encontrado.")
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Rascunho não encontrado.")
   * )
   */
  public function getOne(int $id) {
    try {
      $rascunho = $this->draftService->getRascunhoPorId($id);
      http_response_code(200);
      echo $rascunho->toJson();
    } catch (ModelNotFoundException $e) {
      http_response_code(404);
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
      $this->draftService->excluirRascunho($id);
      http_response_code(204); // No Content
    } catch (ModelNotFoundException $e) {
      http_response_code(404);
      echo json_encode(['message' => 'Rascunho não encontrado.']);
    }
  }
}