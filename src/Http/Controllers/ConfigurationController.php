<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Services\ConfigurationService;
use InvalidArgumentException;

class ConfigurationController {
  private $configService;

  public function __construct() {
    $this->configService = new ConfigurationService();
  }

  // GET /configuration/platforms
  public function getAll() {
    $plataformas = $this->configService->getPlatforms();
    http_response_code(200);
    echo $plataformas->toJson();
  }

  // GET /configuration/platforms/{name}
  public function getOne(string $name) {
    try {
      $plataforma = $this->configService->getPlatformByName($name);
      http_response_code(200);
      echo $plataforma->toJson();
    } catch (InvalidArgumentException $e) {
      http_response_code(404);
      echo json_encode(['message' => $e->getMessage()]);
    }
  }

  // PUT /configuration/platforms
  public function saveAll() {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $this->configService->savePosts($payload);
      http_response_code(200);
      echo json_encode(['message' => 'Plataformas atualizadas com sucesso.']);
    } catch (\Exception $e) {
      \ApiSite\Services\LogService::getInstance()->error('Falha na atualização em massa de plataformas.', ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao atualizar as plataformas.']);
    }
  }

  // PUT /configuration/platforms/{name}
  public function saveOne(string $name) {
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
      $platform = $this->configService->savePost($name, $payload);
      http_response_code(200);
      echo $platform->toJson();
    } catch (InvalidArgumentException $e) {
      http_response_code(400); // Bad Request ou 404 Not Found
      echo json_encode(['message' => $e->getMessage()]);
    } catch (\Exception $e) {
      \ApiSite\Services\LogService::getInstance()->error("Falha ao atualizar a plataforma '$name'.", ['error' => $e->getMessage()]);
      http_response_code(500);
      echo json_encode(['message' => "Ocorreu um erro ao atualizar a plataforma '$name'."]);
    }
  }
}