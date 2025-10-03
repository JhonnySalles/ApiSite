<?php

namespace ApiSite\Http\Controllers;

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
}