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
   * description="Consulta a tabela de postagens e retorna um array paginado de objetos.",
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
   * @OA\JsonContent(type="object", description="Objeto de paginação contendo o histórico.")
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado.")
   * )
   */
  public function history() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 10;

    try {
      $history = $this->publishService->getHistoryPaginated($page, $size);

      http_response_code(200);
      header('Content-Type: application/json');
      echo $history->toJson();

    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha ao buscar histórico.', ['error' => $e->getMessage()]);

      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao buscar o histórico de postagens.']);
    }
  }

}