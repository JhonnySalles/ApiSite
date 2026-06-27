<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\ImageService;
use ApiSite\Services\LogService;
use function Sentry\captureException;

class ImageController {
  private $imageService;

  public function __construct() {
    $this->imageService = new ImageService();
  }

  /**
   * POST /api/images/download-base64
   *
   * Baixa as imagens privadas do B2 Cloud e retorna como Base64.
   */
  public function downloadBase64() {
    try {
      $input = json_decode(file_get_contents('php://input'), true);
      $links = $input['links'] ?? [];

      if (!is_array($links)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'O parâmetro links deve ser um array.']);
        return;
      }

      $images = $this->imageService->downloadImagesAsBase64($links);

      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode(['images' => $images]);

    } catch (\Exception $e) {
      LogService::getInstance()->error('Erro no controller de download de imagens como Base64.', ['error' => $e->getMessage()]);
      captureException($e);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao processar o download das imagens: ' . $e->getMessage()]);
    }
  }

  /**
   * POST /api/images/download-url
   *
   * Gera URLs temporárias e pré-assinadas para as imagens privadas no B2.
   */
  public function downloadUrl() {
    try {
      $input = json_decode(file_get_contents('php://input'), true);
      $links = $input['links'] ?? [];

      if (!is_array($links)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'O parâmetro links deve ser um array.']);
        return;
      }

      $images = $this->imageService->downloadImagesAsUrls($links);

      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode(['images' => $images]);

    } catch (\Exception $e) {
      LogService::getInstance()->error('Erro no controller de download de imagens como URL temporária.', ['error' => $e->getMessage()]);
      captureException($e);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['message' => 'Ocorreu um erro ao processar a geração de URLs temporárias: ' . $e->getMessage()]);
    }
  }
}
