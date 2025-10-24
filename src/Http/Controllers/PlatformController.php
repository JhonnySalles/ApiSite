<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\ConfigurationService;
use ApiSite\Services\LogService;
use Exception;
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
      $aux = $this->fetchTumblrBlogs();

      if ($aux !== null) {
        $this->configService->saveBlogs("tumblr", $aux);
        LogService::getInstance()->info('Sincronização de blogs do Tumblr com o banco local concluída.');
      }
    } catch (Exception $fetchEx) {
      LogService::getInstance()->warning('Não foi possível buscar blogs da API externa. Retornando dados locais.', ['reason' => $fetchEx->getMessage()]);
    }

    try {
      $blogs = $this->configService->getBlogsForPlatform("tumblr");
      http_response_code(200);
      echo $blogs->toJson();
    } catch (InvalidArgumentException $e) {
      LogService::getInstance()->error('Falha ao buscar blogs.', ['error' => $e->getMessage()]);
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);
    } catch (Exception $e) {
      LogService::getInstance()->error('Falha ao buscar blogs.', ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => 'Falha ao buscar blogs.']);
    }
  }

  /**
   * Função privada para buscar os blogs da API Externa Syncronizer.
   * Retorna o array de blogs em caso de sucesso, ou lança uma exceção em caso de falha.
   * @return array
   * @throws Exception Se falhar ao obter token, comunicar com a API ou a resposta for inválida.
   */
  private function fetchTumblrBlogs(): array {
    LogService::getInstance()->info('Tentando buscar blogs do Tumblr na API externa.');
    $authToken = $this->syncAuthService->getToken();
    if (!$authToken)
      throw new Exception('Não foi possível obter token para API Syncronizer ao buscar blogs.');

    $syncApiUrl = $_ENV['POST_SYNCRONIZER_URL'];
    try {
      $response = $this->httpClient->get($syncApiUrl . '/tumblr/blogs', ['headers' => ['Authorization' => 'Bearer ' . $authToken]]);

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody()->getContents(), true);
        LogService::getInstance()->info('Blogs do Tumblr obtidos com sucesso da API externa.');
        return $data['blogs'] ?? [];
      } else {
        $errMsg = "API externa retornou status inesperado para /tumblr/blogs: " . $response->getStatusCode();
        LogService::getInstance()->warning($errMsg);
        throw new Exception($errMsg);
      }
    } catch (RequestException $e) {
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      LogService::getInstance()->error('Erro de comunicação ao buscar blogs do Tumblr na API externa', ['error' => $errorBody]);
      throw new Exception("Falha de comunicação ao buscar blogs do Tumblr: " . $errorBody);
    } catch (Exception $e) {
      LogService::getInstance()->error('Erro inesperado ao tentar buscar blogs externos do Tumblr', ['error' => $e->getMessage()]);
      throw $e;
    }
  }
}