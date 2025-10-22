<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\PublishService;
use ApiSite\Services\LogService;

use Exception;

class HistoryController {
  private $publishService;

  public function __construct() {
    $this->publishService = new PublishService();
  }

  // GET /api/history
  /**
   * @OA\Get(
   * path="/api/history",
   * tags={"Histórico"},
   * summary="Retorna o histórico de postagens paginado.",
   * description="Consulta a tabela de postagens e retorna um array paginado de objetos. Apenas postagens com situação diferente de 'EXCLUIDO' são retornadas.",
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
   * name="page",
   * in="query",
   * description="Número da página a ser retornada.",
   * required=false,
   * @OA\Schema(type="integer", default=1)
   * ),
   * @OA\Parameter(
   * name="size",
   * in="query",
   * description="Número de itens por página.",
   * required=false,
   * @OA\Schema(type="integer", default=10)
   * ),
   * @OA\Response(
   * response=200,
   * description="Operação bem-sucedida.",
   * @OA\JsonContent(
   * type="object",
   * description="Objeto de paginação contendo o histórico de postagens.",
   * @OA\Property(property="current_page", type="integer", example=1),
   * @OA\Property(property="total", type="integer", example=25),
   * @OA\Property(property="per_page", type="integer", example=10),
   * @OA\Property(
   * property="data",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=27),
   * @OA\Property(property="tipo", type="string", example="POST"),
   * @OA\Property(property="situacao", type="string", example="PENDENTE"),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="data", type="string", format="date-time", example="2025-10-14 12:55:23"),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"api", "php"}),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="url", type="string", format="uri", example="https://.../imagem.jpeg"),
   * @OA\Property(property="plataformas", type="array", @OA\Items(type="string"), nullable=true)
   * )
   * ),
   * @OA\Property(
   * property="plataformas",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="plataforma", type="string", example="tumblr"),
   * @OA\Property(property="sucesso", type="boolean", example=false),
   * @OA\Property(property="erro", type="string", nullable=true, example=null)
   * )
   * )
   * )
   * )
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function history() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 10;

    try {
      $historyPaginator = $this->publishService->getHistoryPaginated($page, $size);

      $transformedData = $historyPaginator->getCollection()->map(function ($post) {
        return [
          'id' => $post->id,
          'tipo' => $post->tipo,
          'situacao' => $post->situacao,
          'text' => $post->texto,
          'data' => $post->data_postagem,
          'tags' => $post->tags->pluck('nome'),
          'images' => $post->images->map(function ($image) {
            return [
              'url' => $image->url,
              'plataformas' => $image->plataformas,
            ];
          }),
          'plataformas' => $post->sends->map(function ($send) {
            return [
              'plataforma' => $send->platform ? $send->platform->nome : null,
              'sucesso' => (bool)$send->sucesso,
              'erro' => $send->erro,
            ];
          }),
        ];
      });

      $response = [
        'current_page' => $historyPaginator->currentPage(),
        'data' => $transformedData,
        'first_page_url' => $historyPaginator->url(1),
        'from' => $historyPaginator->firstItem(),
        'last_page' => $historyPaginator->lastPage(),
        'last_page_url' => $historyPaginator->url($historyPaginator->lastPage()),
        'path' => $historyPaginator->path(),
        'per_page' => $historyPaginator->perPage(),
        'to' => $historyPaginator->lastItem(),
        'total' => $historyPaginator->total(),
      ];

      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode($response);

    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha ao buscar histórico.', ['error' => $e->getMessage()]);

      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao buscar o histórico de postagens. ' . $e->getMessage()]);
    }
  }

  // DELETE /api/history/{id}
  /**
   * @OA\Delete(
   * path="/api/history/{id}",
   * tags={"Histórico"},
   * summary="Exclui um registro do histórico.",
   * description="Realiza uma exclusão lógica (soft delete) de uma postagem, alterando sua situação para 'EXCLUIDO'. O registro não será mais retornado nas listagens de histórico.",
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
   * name="id",
   * in="path",
   * required=true,
   * description="ID da postagem a ser excluída.",
   * @OA\Schema(type="integer", example=27)
   * ),
   * @OA\Response(
   * response=204,
   * description="Postagem excluída com sucesso. Nenhuma resposta no corpo."
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(
   * response=404,
   * description="Registro de histórico não encontrado.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Registro de histórico não encontrado.")
   * )
   * ),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function delete(int $id) {
    try {
      $this->publishService->deletePost($id);
      http_response_code(204);
    } catch (ModelNotFoundException $e) {
      http_response_code(404);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Registro de histórico não encontrado.']);

    } catch (Exception $e) {
      LogService::getInstance()->error("Falha ao excluir registro do histórico #{$id}.", ['error' => $e->getMessage()]);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao excluir o registro.']);
    }
  }

}