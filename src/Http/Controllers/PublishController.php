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


  // POST /api/publish
  /**
   * @OA\Post(
   * path="/api/publish",
   * tags={"Postagens"},
   * summary="Cria uma nova postagem para múltiplas plataformas.",
   * description="Recebe os dados de uma postagem (texto, imagens, etc.) e a lista de plataformas onde deve ser publicada. A requisição é processada de forma assíncrona.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Payload contendo os dados da postagem a ser criada.",
   * @OA\JsonContent(
   * type="object",
   * required={"platforms"},
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string", example="tumblr")),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(property="callbackUrl", type="string", format="uri", example="https://meusite.com/callback"),
   * @OA\Property(property="scheduleDate", type="string", format="date-time", example="2025-10-02T16:30:00Z"),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * @OA\Property(property="base64", type="string", format="byte"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string"))
   * )
   * ),
   * @OA\Property(
   * property="platformOptions",
   * type="object",
   * @OA\Property(
   * property="tumblr",
   * type="object",
   * @OA\Property(property="blogName", type="string", example="meu-blog")
   * )
   * )
   * )
   * ),
   * @OA\Response(
   * response=202,
   * description="Requisição aceita. A postagem foi recebida e agendada.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Postagem recebida e agendada para envio."),
   * @OA\Property(property="post_id", type="integer", example=1)
   * )
   * ),
   * @OA\Response(response=400, description="Payload inválido."),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function postsAll() {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!isset($payload['platforms']) || !is_array($payload['platforms']) || empty($payload['platforms'])) {
      http_response_code(400);
      echo json_encode(['message' => 'O campo "platforms" é obrigatório e deve ser um array não vazio.']);
      return;
    }

    try {
      $post = $this->publishService->savePosts($payload);

      http_response_code(202);
      echo json_encode(['message' => 'Postagem recebida e agendada para envio.', 'post_id' => $post->id]);

    } catch (Exception $e) {
      LogService::getInstance()->error('Falha ao criar as postagens.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao processar sua solicitação.',]);
    }
  }

  // POST /api/publish/{platform}
  /**
   * @OA\Post(
   * path="/api/publish/{platform}",
   * tags={"Postagens"},
   * summary="Cria uma nova postagem para uma única plataforma.",
   * description="Recebe os dados de uma postagem e a publica na plataforma especificada na URL.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\Parameter(
   * name="platform",
   * in="path",
   * required=true,
   * description="O nome da plataforma onde a postagem será criada (ex: tumblr).",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Payload contendo os dados da postagem a ser criada.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="text", type="string", example="Postando apenas no Tumblr!"),
   * @OA\Property(property="images", type="array", @OA\Items(type="string", format="byte")),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="api")),
   * @OA\Property(
   * property="platformOptions",
   * type="object",
   * @OA\Property(
   * property="tumblr",
   * type="object",
   * @OA\Property(property="blogName", type="string", example="meu-blog")
   * )
   * )
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Postagem criada com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Postagem para 'tumblr' criada com sucesso."),
   * @OA\Property(property="post_id", type="integer", example=2)
   * )
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma não encontrada."),
   * @OA\Response(response=500, description="Erro interno do servidor.")
   * )
   */
  public function post(string $platform) {
    $payload = json_decode(file_get_contents('php://input'), true);

    try {
      $post = $this->publishService->savePost($platform, $payload);
      http_response_code(200);
      echo json_encode(['message' => "Postagem para '$platform' criada com sucesso.", 'post_id' => $post->id]);

    } catch (InvalidArgumentException $e) {
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);

    } catch (Exception $e) {
      LogService::getInstance()->error("Falha ao criar postagem única para '$platform'.", ['error' => $e->getMessage()]);

      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro interno ao processar a postagem.']);
    }
  }

}