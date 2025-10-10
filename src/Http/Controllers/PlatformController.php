<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\ConfigurationService;
use ApiSite\Services\LogService;
use InvalidArgumentException;

class PlatformController {
  private $configService;

  public function __construct() {
    $this->configService = new ConfigurationService();
  }

  // GET /api/platforms/tumblr/blogs
  /**
   * @OA\Get(
   * path="/api/platform/tumblr/blogs",
   * tags={"Plataformas"},
   * summary="Retorna os blogs associados à plataforma Tumblr.",
   * description="Busca e retorna uma lista de todos os blogs configurados para a plataforma Tumblr.",
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
   * @OA\Property(property="nome", type="string", example="meu-blog"),
   * @OA\Property(property="titulo", type="string", example="Meu Blog Fantástico"),
   * @OA\Property(property="selecionado", type="boolean", example=true)
   * )
   * )
   * ),
   * @OA\Response(response=403, description="Acesso não autorizado (X-API-KEY inválida)."),
   * @OA\Response(response=404, description="Plataforma Tumblr não encontrada ou configurada.")
   * )
   */
  public function getTumblrBlogs() {
    try {
      $blogs = $this->configService->getBlogsForPlatform("tumblr");
      http_response_code(200);
      echo $blogs->toJson();
    } catch (InvalidArgumentException $e) {
      LogService::getInstance()->error('Falha ao buscar blogs.', ['error' => $e->getMessage()]);
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);
    }
  }
}