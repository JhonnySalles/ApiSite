<?php

namespace ApiSite\Services;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SyncAuthService {
  private $httpClient;
  private $baseUrl;
  private $username;
  private $password;
  private $accessToken;
  private $tokenCacheFile;

  public function __construct() {
    $this->baseUrl = $_ENV['POST_SYNCRONIZER_URL'];
    $this->username = $_ENV['POST_SYNCRONIZER_USER'];
    $this->password = $_ENV['POST_SYNCRONIZER_PASSWORD'];
    $this->accessToken = $_ENV['POST_SYNCRONIZER_ACCESS_TOKEN'];
    $this->tokenCacheFile = __DIR__ . '/../../' . $_ENV['POST_SYNCRONIZER_JWT_CACHE_FILE'];

    $this->httpClient = new Client(['base_uri' => $this->baseUrl, 'timeout' => 10.0]);
  }

  /**
   * Obtém um token JWT válido, fazendo login ou refresh se necessário.
   *
   * @return string|null O token JWT ou null em caso de falha.
   */
  public function getToken(): ?string {
    $cachedTokenData = $this->readTokenCache();
    $currentToken = null;
    $needsLogin = true;

    if ($cachedTokenData && isset($cachedTokenData['token']) && isset($cachedTokenData['expiresAt'])) {
      try {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = new DateTime($cachedTokenData['expiresAt'], new DateTimeZone('UTC'));
        $refreshThreshold = (clone $expiresAt)->modify('-1 hour');

        if ($now < $refreshThreshold) {
          LogService::getInstance()->debug('Usando token JWT cacheado (ainda válido).');
          $currentToken = $cachedTokenData['token'];
          $needsLogin = false; // Não precisa logar
        } elseif ($now < $expiresAt) {
          LogService::getInstance()->info('Token JWT próximo de expirar. Tentando renovar...');
          $refreshedTokenData = $this->refreshToken($cachedTokenData['token']);
          if ($refreshedTokenData) {
            $this->writeTokenCache($refreshedTokenData);
            $currentToken = $refreshedTokenData['token'];
            $needsLogin = false; // Não precisa logar, refresh funcionou
            LogService::getInstance()->info('Token JWT renovado com sucesso.');
          } else {
            LogService::getInstance()->warning('Falha ao renovar token JWT. Um novo login será tentado.');
            $needsLogin = true;
          }
        } else {
          LogService::getInstance()->info('Token JWT cacheado expirou. Um novo login será tentado.');
          $needsLogin = true;
        }
      } catch (Exception $e) {
        LogService::getInstance()->error('Erro ao processar data do token cacheado', ['error' => $e->getMessage()]);
        $needsLogin = true;
      }
    } else {
      LogService::getInstance()->info('Nenhum token JWT cacheado válido encontrado. Tentando novo login.');
      $needsLogin = true;
    }

    if ($needsLogin) {
      $newTokenData = $this->login();
      if ($newTokenData && isset($newTokenData['token'])) {
        $this->writeTokenCache($newTokenData);
        $currentToken = $newTokenData['token'];
        LogService::getInstance()->info('Login na API Syncronizer realizado com sucesso.');
      } else {
        LogService::getInstance()->error('Falha CRÍTICA ao obter token JWT do Syncronizer após tentativa de login.');
        $currentToken = null;
      }
    }

    return $currentToken;
  }

  /**
   * Realiza o login na API Syncronizer.
   */
  private function login(): ?array {
    try {
      $response = $this->httpClient->post('/auth/login', ['json' => ['username' => $this->username, 'password' => $this->password, 'accessToken' => $this->accessToken,]]);

      if ($response->getStatusCode() === 200)
        return json_decode($response->getBody()->getContents(), true);
    } catch (RequestException $e) {
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      LogService::getInstance()->error('Erro no login do Syncronizer API', ['error' => $errorBody]);
    }
    return null;
  }

  /**
   * Renova um token JWT existente.
   */
  private function refreshToken(string $currentToken): ?array {
    LogService::getInstance()->info('Tentando renovar token JWT na API Syncronizer...');
    try {
      $response = $this->httpClient->post('/auth/token/refresh', [
        'headers' => [
          'Authorization' => 'Bearer ' . $currentToken
        ],
        'json' => [
          'accessToken' => $this->staticAccessToken
        ]
      ]);

      $statusCode = $response->getStatusCode();
      $bodyContent = $response->getBody()->getContents();

      if ($statusCode >= 200 && $statusCode < 300) {
        $body = json_decode($bodyContent, true);
        if (isset($body['accessToken'])) {
          $expires = (new DateTime('now', new DateTimeZone('UTC')))->modify('+24 hours');
          LogService::getInstance()->debug('Refresh Syncronizer OK', ['status' => $statusCode]);
          return [
            'token' => $body['accessToken'],
            'expiresAt' => $expires->format('Y-m-d\TH:i:s.v\Z')
          ];
        } else {
          LogService::getInstance()->error('Resposta de refresh token inválida: chave "accessToken" ausente.', ['response' => $bodyContent]);
        }
      } else {
        LogService::getInstance()->error('Erro no refresh token do Syncronizer API', ['status' => $statusCode, 'response' => $bodyContent]);
      }
    } catch (RequestException $e) {
      LogService::getInstance()->error('Erro de rede no refresh token do Syncronizer API', ['error' => $e->getMessage()]);
    } catch (Exception $e) {
      LogService::getInstance()->error('Erro inesperado no refresh token do Syncronizer API', ['error' => $e->getMessage()]);
    }
    return null;
  }

  private function readTokenCache(): ?array {
    if (file_exists($this->tokenCacheFile)) {
      $content = file_get_contents($this->tokenCacheFile);
      return json_decode($content, true);
    }
    return null;
  }

  private function writeTokenCache(array $tokenData): void {
    $dir = dirname($this->tokenCacheFile);
    if (!is_dir($dir))
      mkdir($dir, 0755, true);

    file_put_contents($this->tokenCacheFile, json_encode($tokenData));
  }
}