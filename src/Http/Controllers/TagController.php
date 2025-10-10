<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\LogService;
use ApiSite\Services\PublishService;

class TagController {
  private $publishService;

  public function __construct() {
    $this->publishService = new PublishService();
  }

  // GET /api/tags
  /**
   * @OA\Get(
   * path="/api/tags",
   * tags={"Tags"},
   * summary="Retorna uma lista de todas as tags únicas.",
   * description="Busca no banco de dados todas as tags que já foram utilizadas, ordena alfabeticamente e retorna um array de strings. Ideal para popular comboboxes e campos de autocompletar.",
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
   * description="Lista de tags retornada com sucesso.",
   * @OA\JsonContent(
   * type="array",
   * @OA\Items(type="string"),
   * example={"api", "development", "php", "rascunho", "tumblr"}
   * )
   * ),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function tags() {
    try {
      $tags = $this->publishService->getTags();
      $tagNames = $tags->pluck('tag');

      http_response_code(200);
      header('Content-Type: application/json');
      echo $tagNames->toJson();

    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha ao buscar tags.', ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao buscar as tags. ' . $e->getMessage()]);
    }
  }
}