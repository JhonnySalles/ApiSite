<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\LogService;
use Exception;

class HealthController {
  /**
   * @OA\Get(
   * path="/health",
   * tags={"Status"},
   * summary="Verifica a saúde da API.",
   * description="Endpoint público utilizado para monitoramento. Retorna uma mensagem de sucesso se a API estiver operacional.",
   * @OA\Response(
   * response=200,
   * description="A API está funcionando corretamente.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(
   * property="message",
   * type="string",
   * example="A API está funcionando! Acesse /docs para ver a documentação."
   * )
   * )
   * )
   * )
   */
  public function check() {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'A API está funcionando! Acesse /docs para ver a documentação, ou /docs/api-spec para o json.']);
  }

  /**
   * @OA\Get(
   * path="/test-sentry",
   * tags={"Status"},
   * summary="Gera um erro de teste para o Sentry.",
   * description="Endpoint público que lança uma exceção propositalmente para verificar se a integração com o Sentry está funcionando.",
   * @OA\Response(
   * response=500,
   * description="Erro interno do servidor (gerado para teste)."
   * )
   * )
   */
  public function testSentryError() {
    LogService::getInstance()->warning('Gerando um erro de teste para o Sentry...');
    throw new Exception('Este é um erro de teste para verificar a integração do Sentry em ' . date('Y-m-d H:i:s'));
  }
}