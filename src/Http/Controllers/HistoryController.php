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
   * @OA\Property(property="pagina", type="integer", example=1),
   * @OA\Property(property="total", type="integer", example=150),
   * @OA\Property(
   * property="data",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="id", type="integer", example=42),
   * @OA\Property(property="tipo", type="string", example="POST"),
   * @OA\Property(property="situacao", type="string", example="PENDENTE"),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo do histórico."),
   * @OA\Property(property="data", type="string", format="date-time", description="Data de agendamento ou criação da postagem.", example="2025-10-02T16:30:00Z"),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="histórico")),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="url", type="string", format="uri", example="https://seu-bucket.supabase.co/.../imagem.png"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string", example="tumblr"))
   * )
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