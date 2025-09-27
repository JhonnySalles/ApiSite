<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\PublishService;
use ApiSite\Services\LogService;

use Exception;

class PublishController {
  private $publishService;

  public function __construct() {
    $this->publishService = new PublishService();
  }

  /**
   * Realiza a postagem em várias platafornas selecionadas.
   * @param string $plataforma O nome da plataforma vindo da URL.
   */
  public function postsAll() {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!isset($payload['platforms']) || !is_array($payload['platforms']) || empty($payload['platforms'])) {
      http_response_code(400);
      echo json_encode(['message' => 'O campo "platforms" é obrigatório e deve ser um array não vazio.']);
      return;
    }

    try {
      $postagem = $this->publishService->savePosts($payload);

      http_response_code(202);
      echo json_encode(['message' => 'Postagem recebida e agendada para envio.', 'post_id' => $postagem->id]);

    } catch (Exception $e) {
      LogService::getInstance()->error('Falha ao criar as postagens.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao processar sua solicitação.',]);
    }
  }

  /**
   * Realiza a postagem em uma única plataforma.
   * @param string $plataforma O nome da plataforma vindo da URL.
   */
  public function post(string $plataforma) {
    $payload = json_decode(file_get_contents('php://input'), true);

    try {
      $postagem = $this->publishService->savePost($plataforma, $payload);
      http_response_code(200);
      echo json_encode(['message' => "Postagem para '$plataforma' criada com sucesso.", 'post_id' => $postagem->id]);

    } catch (InvalidArgumentException $e) {
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);

    } catch (Exception $e) {
      LogService::getInstance()->error("Falha ao criar postagem única para '$plataforma'.", ['error' => $e->getMessage()]);

      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro interno ao processar a postagem.']);
    }
  }

  /**
   * @OA\Get(
   * path="/historico",
   * tags={"Postagens"},
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
      $historico = $this->publishService->getHistoryPaginated($page, $size);

      http_response_code(200);
      header('Content-Type: application/json');
      echo $historico->toJson();

    } catch (\Exception $e) {
      LogService::getInstance()->error('Falha ao buscar histórico.', ['error' => $e->getMessage()]);

      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao buscar o histórico de postagens.']);
    }
  }

}