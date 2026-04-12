# Publicação de Posts - PublishController

## 🎯 Objetivo / Contexto

Responsável por receber os dados de uma nova postagem do Frontend, salvar localmente no banco de dados e orquestrar o envio para múltiplas plataformas (como Tumblr, X, etc) comunicando-se com a API externa `PostSynchronizer`.

## 🧩 Arquivos e Componentes Core

- **Controller Principal:** `src\Http\Controllers\PublishController.php`
- **Services Principais:** `PublishService.php`, `ImageService.php`, `SyncAuthService.php`
- **Models Envolvidos:** `Post`, `Send`, `Platform`, `Tag`
- **WebSockets:** Usa `ElephantIO\Client` para ouvir eventos via Socket.IO v4.

## ⚙️ Regras de Negócio e Lógica (Core Logic)

- **Upload de Imagens:** As imagens chegam em formato Base64 ou URL. O `ImageService` extrai os Base64 e os envia para um bucket no Supabase (S3), devolvendo as URLs públicas, ou os prepara para envio na API de Syncronizer.
- **Comunicação Assíncrona:** O `POST /api/publish` é assíncrono. O backend salva a requisição, envia para a API PostSynchronizer, e abre uma conexão WebSocket (Socket.IO) aguardando atualizações (`progressUpdate` e `taskCompleted`).
- **Notificações via Webhook:** À medida que o PostSynchronizer responde via WebSocket, o backend atualiza as tabelas (`envios`) e dispara Webhooks (`FRONTEND_WEBHOOK_URL` assinado via HMAC-SHA256) avisando o frontend.
- **Modo de Teste:** Caso a env `IGNORAR_POST` seja verdadeira ou `APP_ENV` = testing, não conecta com APIs externas, apenas simula falhas (random) e sucesso, atualizando o DB de forma simulada.

## 🔗 Endpoints e Fluxo

- **POST /api/publish:**
  - Inicia envio em lote. Retorna `202 Accepted` imediato e processa via Webhooks.
- **POST /api/publish/{platform}:**
  - Postagem síncrona/individual para uma única plataforma específica.

## 🌍 Integrações / Dependências Externas (Referência)

- **API Syncronizer:** `POST_SYNCRONIZER_URL` (POST /publish-all/post ou POST /{platform}/post).
- **Autenticação Ext:** `SyncAuthService` para pegar o Bearer token.
- **Storage (S3):** Supabase bucket (`SitePost`).
- **Variáveis .env chave:** `FRONTEND_WEBHOOK_URL`, `FRONTEND_WEBHOOK_SECRET`, `POST_SYNCRONIZER_ACCESS_TOKEN`.
- **Monitoramento:** `\Sentry\captureException` para registrar as falhas.
