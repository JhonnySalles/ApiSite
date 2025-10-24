<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Models\Post;
use ApiSite\Models\Send;
use ApiSite\Services\ImageService;
use ApiSite\Services\LogService;
use ApiSite\Services\PublishService;
use ApiSite\Services\SyncAuthService;
use Exception;
use ReflectionClass;
use InvalidArgumentException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use ElephantIO\Client as ElephantIOClient;
use ElephantIO\Engine\Packet;
use ElephantIO\Exception\SocketException;

class PublishController {

  private const WEBSOCKET_IDLE_TIMEOUT = 15 * 60; // 15 minutos

  private $publishService;
  private $syncAuthService;
  private $httpClient;

  public function __construct() {
    $this->publishService = new PublishService();
    $this->syncAuthService = new SyncAuthService();
    $this->httpClient = new HttpClient(['timeout' => 15.0]);
  }


  // POST /api/publish

  /**
   * @OA\Post(
   * path="/api/publish",
   * tags={"Postagens"},
   * summary="Inicia o envio de uma postagem para múltiplas plataformas.",
   * description="Recebe os dados para postagem, após salvar será iniciado o processo de envio para a API de postagem (PostSynchronizer). A API responde com 202 Accepted e o progresso de cada plataforma e o resultado final serão enviados para o frontend via Webhook configurado.",
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
   * @OA\RequestBody(
   * required=true,
   * description="Payload contendo os dados da postagem a ser criada/atualizada e enviada.",
   * @OA\JsonContent(
   * type="object",
   * required={"platforms"},
   * @OA\Property(property="id", type="integer", description="ID da postagem local para atualização (opcional).", example=1),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string", example="tumblr"), description="Plataformas de destino."),
   * @OA\Property(property="text", type="string", example="Este é um texto de exemplo."),
   * @OA\Property(property="tags", type="array", @OA\Items(type="string", example="php")),
   * @OA\Property(property="scheduleDate", type="string", format="date-time", example="2025-10-22T14:30:00Z", description="Data para agendamento (opcional)."),
   * @OA\Property(
   * property="images",
   * type="array",
   * @OA\Items(
   * type="object",
   * description="Pode conter 'base64' (Data URI) ou 'url'. 'base64' tem prioridade.",
   * @OA\Property(property="base64", type="string", format="byte"),
   * @OA\Property(property="url", type="string", format="uri"),
   * @OA\Property(property="platforms", type="array", @OA\Items(type="string"), description="Plataformas específicas para esta imagem (opcional).")
   * )
   * ),
   * @OA\Property(
   * property="platformOptions",
   * type="object",
   * description="Opções específicas para cada plataforma.",
   * @OA\Property(property="tumblr", type="object", @OA\Property(property="blogName", type="string", example="meu-blog"))
   * )
   * )
   * ),
   * @OA\Response(
   * response=202,
   * description="Requisição aceita. O envio foi iniciado e o feedback será via Webhook.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Envio iniciado. O progresso será notificado via webhook."),
   * @OA\Property(property="post_id", type="integer", example=1)
   * )
   * ),
   * @OA\Response(response=400, description="Payload inválido."),
   * @OA\Response(response=401, description="Não autorizado (Token JWT inválido)."),
   * @OA\Response(response=403, description="Acesso proibido (X-API-KEY inválida)."),
   * @OA\Response(response=500, description="Erro interno ao iniciar o processo de envio.")
   * )
   */
  public function postsAll() {
    $payload = json_decode(file_get_contents('php://input'), true);
    $post = null;
    $client = null;

    if (!isset($payload['platforms']) || !is_array($payload['platforms']) || empty($payload['platforms'])) {
      http_response_code(400);
      echo json_encode(['message' => 'O campo "platforms" é obrigatório e deve ser um array não vazio.']);
      return;
    }

    try {
      $post = $this->publishService->savePosts($payload);
      LogService::getInstance()->info("Post {$post->id} salvo. Iniciando o envio.");

      if (filter_var($_ENV['IGNORAR_POST'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->simulateExternalPost($post);
        http_response_code(202);
        echo json_encode(['message' => 'Postagem salva (envio simulado).', 'post_id' => $post->id]);
        return;
      }

      $authToken = $this->syncAuthService->getToken();
      if (!$authToken)
        throw new Exception("Não foi possivel obter o token de autorização em Syncronizer API.");

      $syncApiBaseUrl = $_ENV['POST_SYNCRONIZER_URL'];
      LogService::getInstance()->info("Conectando ao Socket.IO via create() em: {$syncApiBaseUrl}");

      $client = ElephantIOClient::create($syncApiBaseUrl, [
        'client' => ElephantIOClient::CLIENT_4X,
        'logger' => LogService::getInstance()
      ]);

      $processingError = null;
      $post->loadMissing(['sends.platform', 'tags', 'images']);
      $allPlatforms = $post->sends->pluck('platform.nome')->toArray();

      $summaryReceived = false;
      $socketId = null;

      LogService::getInstance()->info("Inicializando conexão Socket.IO...");
      $client->connect();
      $client->of('/');


      ////////////////////////// obtenção do id por reflexão-pega o errado

      if (!$client->getEngine()->connected())
        throw new Exception("Socket.IO -- Falha inesperada ao conectar.");

      LogService::getInstance()->info("Tentando obter SID do Namespace via Reflection...");
      try {
        $engine = $client->getEngine();

        if (!$engine->connected())
          throw new Exception("Engine não está conectado após initialize/of.");

        $reflectionEngine = new ReflectionClass($engine);
        $sessionProperty = $reflectionEngine->getProperty('session');

        if (!$sessionProperty->isInitialized($engine)) {
          LogService::getInstance()->warning("Propriedade 'session' não inicializada imediatamente. Tentando drain...");
          $client->drain(0.5);
          if (!$sessionProperty->isInitialized($engine))
            throw new Exception("Propriedade 'session' do Engine não inicializada mesmo após drain.");
        }

        $sessionObject = $sessionProperty->getValue($engine);

        if ($sessionObject instanceof \ElephantIO\Engine\Session) {
          $reflectionSession = new ReflectionClass($sessionObject);
          $valuesProperty = $reflectionSession->getProperty('values');

          if (!$valuesProperty->isInitialized($sessionObject))
            throw new Exception("Propriedade 'values' do Session não inicializada.");

          $valuesArray = $valuesProperty->getValue($sessionObject);

          if (is_array($valuesArray) && isset($valuesArray['id']))
            $socketId = $valuesArray['id'];
        } else
          throw new Exception("Propriedade 'session' não é um objeto Session válido.");

      } catch (\ReflectionException $refEx) {
        LogService::getInstance()->error("Erro de Reflection ao tentar obter SID", ['error' => $refEx->getMessage()]);
      } catch (Exception $e) {
        LogService::getInstance()->error("Erro ao tentar obter SID via Reflection", ['error' => $e->getMessage()]);
      }////////////////// fim da funçao com problema.

      if (!$socketId) {
        LogService::getInstance()->error("Falha Crítica na Conexão/Handshake: Não foi possível extrair o Socket ID (SID) via Reflection.");
        try { $client->disconnect(); } catch (Exception $ignore) {}
        throw new Exception("Falha na conexão inicial do WebSocket: SID não encontrado.");
      }

      LogService::getInstance()->info("Socket.IO Connected. ID Sessão: " . $socketId);
      $GLOBALS['postIdForWebhook'] = $post->id;
      $GLOBALS['processedPlatforms'] = [];
      $GLOBALS['lastActivityTime'] = time();

      $this->sendToPublishAllApi($post, $payload, $authToken, $socketId);
      $startTime = time();

      LogService::getInstance()->info("Aguardando eventos do Socket.IO via drain()...");
      while ($client->getEngine()->connected()) {
        $packet = null;
        try {
          $packet = $client->drain(1);
        } catch (SocketException $sockEx) {
          LogService::getInstance()->error("Erro de Socket durante drain(): " . $sockEx->getMessage());
          $processingError = $sockEx;
          break;
        } catch (Exception $e) {
          LogService::getInstance()->error("Erro durante drain(): " . $e->getMessage());
          $processingError = $e;
          break;
        }

        if ($packet instanceof Packet) {
          $GLOBALS['lastActivityTime'] = time();
          LogService::getInstance()->debug("Socket.IO drain() received packet:", ['type' => $packet->type, 'name' => $packet->name ?? 'N/A', 'data' => $packet->data ?? null]);

          if ($packet->type === 2 && isset($packet->name)) {
            $eventName = $packet->name;
            $eventData = $packet->data ?? [];

            LogService::getInstance()->info("Socket.IO Evento Recebido:", ['name' => $eventName, 'data' => $eventData]);

            if ($eventName === 'progressUpdate') {
              if (isset($eventData['platform'])) {
                $this->updateSendStatus($post->id, $eventData);
                $GLOBALS['processedPlatforms'][$eventData['platform']] = true;
              }
            } elseif ($eventName === 'taskCompleted') {
              LogService::getInstance()->info("Socket.IO 'taskCompleted' recebido.");
              $this->sendWebhookUpdate($post->id, 'summary', $eventData);
              $summaryReceived = true;
            }
          }
        } else if ($packet !== null){
          LogService::getInstance()->debug("Socket.IO drain() did not return an event Packet.", ['return' => $packet]);
          $GLOBALS['lastActivityTime'] = time(); // Conta como atividade
        } else
          LogService::getInstance()->debug("Socket.IO drain() returned null.");

        if ($summaryReceived) {
          LogService::getInstance()->info("Recebido Summary, fechando a conexão.");
          break;
        }

        if ($processingError) {
          LogService::getInstance()->error("Erro detectado durante o loop Socket.IO.");
          break;
        }

        if ((time() - $GLOBALS['lastActivityTime']) > self::WEBSOCKET_IDLE_TIMEOUT) {
          LogService::getInstance()->warning("Timeout GERAL/INATIVIDADE ({$this->WEBSOCKET_IDLE_TIMEOUT}s) alcançado.");
          $processingError = new Exception("Timeout esperando resposta do WebSocket.");
          LogService::getInstance()->info("Gerando sumário manual devido ao timeout.");
          $successfulPlatforms = [];
          $failedPlatforms = $allPlatforms;
          if (!empty($GLOBALS['processedPlatforms'])) {
            $processedNames = array_keys($GLOBALS['processedPlatforms']);
            $successSends = Send::where('postagem_id', $post->id)->where('sucesso', true)->whereHas('platform', fn($q) => $q->whereIn('nome', $processedNames))->with('platform:id,nome')->get();
            $successfulPlatforms = $successSends->pluck('platform.nome')->toArray();
            $failedPlatforms = array_diff($allPlatforms, $successfulPlatforms);
          }
          $manualSummaryData = [
            'type' => 'summary',
            'summary' => [
              'status' => 'completed_with_timeout',
              'completedAt' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
              'successful' => array_values($successfulPlatforms),
              'failed' => array_values($failedPlatforms),
              'reason' => 'timeout'
            ]
          ];
          $this->sendWebhookUpdate($post->id, 'summary', $manualSummaryData);
          break;
        }

        usleep(100000);
      }

      if (!$summaryReceived && !$processingError && !$client->getEngine()->connected()) {
        LogService::getInstance()->warning("Loop encerrado com conexão perdida antes do sumário.");
        $processingError = new Exception("WebSocket connection lost unexpectedly before completion.");
        $manualSummaryData = [
          'type' => 'summary',
          'summary' => [
            'status' => 'completed_with_timeout',
            'completedAt' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
            'reason' => 'timeout'
          ]
        ];
        $this->sendWebhookUpdate($post->id, 'summary', $manualSummaryData);
      }

      LogService::getInstance()->info("Socket.IO event loop finished.");
      $client->disconnect();

      if ($processingError)
        throw $processingError;

      http_response_code(202);
      echo json_encode(['message' => 'Envio processado. Verifique o webhook para detalhes.', 'post_id' => $post->id]);

    } catch (SocketException $connectEx) {
      LogService::getInstance()->error('Erro fatal de Socket.IO (conexão/leitura).', [
        'post_id' => $post->id ?? null,
        'error_message' => $connectEx->getMessage(),
        'error_file' => $connectEx->getFile(),
        'error_line' => $connectEx->getLine(),
        // 'trace' => $connectEx->getTraceAsString() // Opcional: Trace completo (pode ser muito grande)
      ]);
      http_response_code(500);
      echo json_encode(['message' => 'Falha na comunicação com o serviço de publicação.', 'details' => $connectEx->getMessage()]);
    } catch (Exception $e) {
      LogService::getInstance()->error('Erro geral fatal no processo postsAll com Socket.IO v4.', [
        'post_id' => $post->id ?? null,
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        // 'trace' => $e->getTraceAsString() // Opcional: Trace completo (pode ser muito grande)
      ]);
      if (isset($client)) { try { $client->disconnect(); } catch (Exception $ignore) {} }
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro ao processar o envio da postagem.', 'details' => $e->getMessage()]);
    } finally {
      unset($GLOBALS['postIdForWebhook'], $GLOBALS['processedPlatforms'], $GLOBALS['lastActivityTime']);
    }

  }

  // POST /api/publish/{platform}

  /**
   * @OA\Post(
   * path="/api/publish/{platform}",
   * tags={"Postagens"},
   * summary="Cria uma nova postagem para uma única plataforma.",
   * description="Recebe os dados para postagem, após salvar será iniciado o processo de envio para a API de postagem (PostSynchronizer). A API responde com após receber o retorno da postagem na plataforma.",
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
    $post = null;

    try {
      $post = $this->publishService->savePost($platform, $payload);
      LogService::getInstance()->info("Post {$post->id} salvo/atualizado. Iniciando envio para {$platform}.");

      $result = $this->sendToPublishApiPlatform($post, $platform, $payload['platformOptions'] ?? null);

      http_response_code($result['statusCode']);
      header('Content-Type: application/json');
      echo json_encode($result['body']);

    } catch (InvalidArgumentException $e) {
      http_response_code(400);
      LogService::getInstance()->warning("Erro de validação ao postar em {$platform}", ['post_id' => $post->id ?? null, 'error' => $e->getMessage()]);
      echo json_encode(['message' => $e->getMessage()]);

    } catch (Exception $e) {
      LogService::getInstance()->error("Falha geral ao postar em {$platform}.", ['post_id' => $post->id ?? null, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      http_response_code(500);
      echo json_encode(['message' => 'Ocorreu um erro interno ao processar a postagem. ' . $e->getMessage()]);
    }
  }

  /**
   * Envia a postagem para uma plataforma específica na API Syncronizer.
   * Assume que a postagem local já existe/foi atualizada.
   * Prepara as imagens (baixando/formatando base64) antes de enviar.
   *
   * @param Post $post O objeto Eloquent da postagem local.
   * @param string $platformName O nome da plataforma de destino.
   * @param array|null $platformOptions Opções específicas da plataforma (ex: tumblr.blogName).
   * @return array ['statusCode' => int, 'body' => mixed] Resposta da API externa.
   * @throws Exception Se ocorrer erro de autenticação ou comunicação.
   * @throws InvalidArgumentException Se dados necessários estiverem faltando (ex: blogName).
   */
  private function sendToPublishApiPlatform(Post $post, string $platformName, ?array $platformOptions): array {
    $platformName = $this->publishService->resolvePlatformAlias($platformName);

    if (filter_var($_ENV['IGNORAR_POST'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
      LogService::getInstance()->info("Simulando envio individual para Post {$post->id}, Plataforma {$platformName}");
      sleep(rand(1, 2));
      $fails = (rand(1, 100) <= 15);
      $statusCode = $fails ? 500 : ($platformName === 'tumblr' ? (rand(0, 1) ? 200 : 201) : 200);
      $body = $fails ? ['error' => 'Exceção de teste simulada'] : ['message' => 'Simulado com sucesso'];
      $this->publishService->updatePostSituation($post->id, $platformName, !$fails, $fails ? $body['error'] : null);
      return ['statusCode' => $statusCode, 'body' => $body];
    }


    $authToken = $this->syncAuthService->getToken();
    if (!$authToken)
      throw new Exception("Não foi possível obter token para API Syncronizer.");

    $originalImagesPayload = $post->images->map(fn($img) => ['url' => $img->url, 'platforms' => $img->plataformas])->toArray();
    $imageService = new ImageService();
    $preparedImages = $imageService->prepareImagesForUpload($originalImagesPayload);

    $syncApiUrl = $_ENV['POST_SYNCRONIZER_URL'];
    $endpoint = "/{$platformName}/post";
    $payloadForSyncApi = ['instanceId' => 'POST_SYNCRONIZER', 'postId' => (string)$post->id, 'text' => $post->texto, 'tags' => $post->tags->pluck('nome')->toArray(), 'images' => array_map(fn($img) => $img['base64'], $preparedImages),];

    if ($platformName === 'tumblr') {
      $tumblrOptions = $platformOptions['tumblr'] ?? null;
      if (empty($tumblrOptions['blogName']))
        throw new InvalidArgumentException("O 'blogName' é obrigatório para postar no Tumblr.");

      $payloadForSyncApi['blogName'] = $tumblrOptions['blogName'];
    }

    $statusCode = 500;
    $body = null;
    $errorMsg = null;
    $success = false;

    try {
      $response = $this->httpClient->post($syncApiUrl . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $authToken, 'Content-Type' => 'application/json',], 'json' => $payloadForSyncApi]);

      $statusCode = $response->getStatusCode();
      $body = json_decode($response->getBody()->getContents(), true);
      $success = ($statusCode === 200 || $statusCode === 201);
      LogService::getInstance()->info("Envio individual para Post {$post->id}, Plataforma {$platformName} concluído.", ['status' => $statusCode, 'response' => $body]);

    } catch (RequestException $e) {
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
      $errorMsg = "Erro na API externa: " . $errorBody;
      $body = ['error' => $errorMsg];
      LogService::getInstance()->error("Erro no envio individual para Post {$post->id}, Plataforma {$platformName}", ['error' => $errorBody, 'status' => $statusCode]);
    } catch (Exception $e) {
      $errorMsg = "Erro interno: " . $e->getMessage();
      $body = ['error' => $errorMsg];
      LogService::getInstance()->error("Erro interno no envio individual para Post {$post->id}, Plataforma {$platformName}", ['error' => $errorMsg]);
    }

    $this->publishService->updatePostSituation($post->id, $platformName, $success, $errorMsg);

    return ['statusCode' => $statusCode, 'body' => $body];
  }


  /**
   * Envia a requisição POST para a API Syncronizer.
   * Chamado DEPOIS que a conexão WebSocket é estabelecida e o socketId é recebido.
   */
  private function sendToPublishAllApi(Post $post, array $originalPayload, string $authToken, string $socketId) {
    LogService::getInstance()->info("Enviando POST para /publish-all/post com socketId: {$socketId}");
    $syncApiUrl = $_ENV['POST_SYNCRONIZER_URL'];

    $preparedImagesForSyncApi = [];
    try {
      $imageService = new ImageService();
      $preparedImagesForSyncApi = $imageService->prepareImagesForUpload($originalPayload['images'] ?? null);
    } catch (Exception $e) {
      LogService::getInstance()->error('Erro ao preparar imagens para API Syncronizer', ['error' => $e->getMessage(), 'post_id' => $post->id]);
      throw new Exception("Falha ao preparar imagens: " . $e->getMessage());
    }

    $publishPayload = ['platforms' => $originalPayload['platforms'], 'instanceId' => $_ENV['POST_SYNCRONIZER_ACCESS_TOKEN'], 'postId' => (string)$post->id, 'text' => $post->texto, 'images' => $preparedImagesForSyncApi, 'tags' => $post->tags->pluck('nome')->toArray(), 'socketId' => $socketId, 'platformOptions' => $originalPayload['platformOptions'] ?? null,];

    try {
      $response = $this->httpClient->post($syncApiUrl . '/publish-all/post', ['headers' => ['Authorization' => 'Bearer ' . $authToken, 'Content-Type' => 'application/json',], 'json' => $publishPayload]);

      if ($response->getStatusCode() !== 202)
        LogService::getInstance()->error('API Syncronizer retornou status inesperado para /publish-all/post', ['status' => $response->getStatusCode(), 'body' => $response->getBody()->getContents()]); else
        LogService::getInstance()->info('Requisição POST para /publish-all/post aceita (202). Aguardando WebSocket...');

    } catch (RequestException $e) {
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      LogService::getInstance()->error('Erro ao enviar POST para Syncronizer /publish-all/post', ['error' => $errorBody]);
      throw new Exception("Falha ao comunicar com a API de publicação: " . $errorBody);
    }
  }

  /**
   * Atualiza o status de um envio específico no banco de dados.
   */
  private function updateSendStatus(int $postId, array $progressData) {
    try {
      $post = Post::find($postId);
      if (!$post)
        return;

      $send = $post->sends()->whereHas('platform', function ($query) use ($progressData) {
        $query->where('nome', $progressData['platform']);
      })->first();

      if ($send) {
        $send->sucesso = ($progressData['status'] === 'success');
        $send->erro = $progressData['error'] ?? null;
        $send->save();
        LogService::getInstance()->info("Status do envio atualizado para Post {$postId}, Plataforma {$progressData['platform']}");
      } else
        LogService::getInstance()->warning("Registro de envio não encontrado para Post {$postId}, Plataforma {$progressData['platform']}");
    } catch (Exception $e) {
      LogService::getInstance()->error("Erro ao atualizar status do envio via WebSocket", ['post_id' => $postId, 'platform' => $progressData['platform'] ?? 'N/A', 'error' => $e->getMessage()]);
    }
  }

  /**
   * Envia uma atualização de status para o webhook configurado no frontend.
   *
   * @param int $postId ID da postagem local.
   * @param string $type Tipo de atualização ('progress' ou 'summary').
   * @param array $data Dados recebidos do WebSocket.
   */
  private function sendWebhookUpdate(int $postId, string $type, array $data) {
    $webhookUrl = $_ENV['FRONTEND_WEBHOOK_URL'] ?? null;
    $webhookSecret = $_ENV['FRONTEND_WEBHOOK_SECRET'] ?? null;

    if (empty($webhookUrl) || empty($webhookSecret)) {
      LogService::getInstance()->warning('Webhook URL ou Secret não configurados no .env. Feedback não enviado.', ['post_id' => $postId]);
      return;
    }

    $payload = [];
    $headers = ['Content-Type' => 'application/json', 'X-Webhook-Secret' => $webhookSecret];

    if ($type === 'progress' && isset($data['platform']))
      $payload = ['type' => 'progress', 'postId' => $postId, 'platform' => $data['platform'], 'status' => ($data['status'] === 'success') ? 'success' : 'failed', 'error' => $data['error'] ?? null,]; elseif ($type === 'summary' && isset($data['summary']))
      $payload = ['type' => 'summary', 'postId' => $postId, 'status' => 'completed', 'summary' => $data['summary'],];
    else {
      LogService::getInstance()->warning('Tipo de webhook desconhecido ou dados inválidos recebidos.', ['type' => $type, 'data' => $data]);
      return;
    }

    try {
      LogService::getInstance()->info("Enviando webhook '{$type}' para {$webhookUrl}", ['post_id' => $postId]);
      $this->httpClient->post($webhookUrl, ['headers' => $headers, 'json' => $payload]);
      LogService::getInstance()->info("Webhook '{$type}' enviado com sucesso.", ['post_id' => $postId]);

    } catch (RequestException $e) {
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
      LogService::getInstance()->error("Falha ao enviar webhook '{$type}' para {$webhookUrl}", ['post_id' => $postId, 'error' => $errorBody, 'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null]);
    } catch (Exception $e) {
      LogService::getInstance()->error("Erro inesperado ao enviar webhook '{$type}'", ['post_id' => $postId, 'error' => $e->getMessage()]);
    }
  }


  /**
   * Simula o processo de postagem externa para testes.
   */
  private function simulateExternalPost(Post $post) {
    LogService::getInstance()->info("Simulando envio externo para Post {$post->id}");
    $post->loadMissing(['sends.platform', 'tags']);

    $successfulPlatforms = [];
    $failedPlatforms = [];

    foreach ($post->sends as $send) {
      $platformName = $send->platform->nome;
      $delay = rand(1, 2);
      sleep($delay);

      $fails = (rand(1, 100) <= 30);
      $errorMsg = $fails ? "Exceção de teste simulada para " . $platformName : null;

      $this->publishService->updatePostSituation($post->id, $platformName, !$fails, $errorMsg);

      $progressData = ['platform' => $platformName, 'status' => $fails ? 'error' : 'success', 'error' => $errorMsg];

      $this->sendWebhookUpdate($post->id, 'progress', $progressData);

      if ($fails)
        $failedPlatforms[] = $platformName; else
        $successfulPlatforms[] = $platformName;

      LogService::getInstance()->info("Simulação: Plataforma {$platformName} - " . ($fails ? "Falha" : "Sucesso"));
    }

    $summaryData = ['type' => 'summary', 'summary' => ['successful' => $successfulPlatforms, 'failed' => $failedPlatforms]];
    $this->sendWebhookUpdate($post->id, 'summary', $summaryData);
    LogService::getInstance()->info("Simulação concluída para Post {$post->id}");
  }

}