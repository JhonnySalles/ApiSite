<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\Platform;
use ApiSite\Models\Post;
use ApiSite\Models\User;
use GuzzleHttp\Client;
use Tests\Support\HttpServer;
use Tests\TestCase;

class PublishControllerIntegrationTest extends TestCase {
    private static $appServer;
    private static $mockApiServer;
    private $httpClient;
    private $token;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $appPort = (int)($_ENV['MOCK_SERVER_PORT'] ?? 8080);
        $mockPort = 4000;

        self::$appServer = new HttpServer('localhost', $appPort, 'public');
        self::$mockApiServer = new HttpServer('localhost', $mockPort, 'tests/Support/ExternalSyncMock.php');

        try {
            self::$appServer->start();
            self::$mockApiServer->start();
        } catch (\Exception $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void {
        if (self::$appServer) self::$appServer->stop();
        if (self::$mockApiServer) self::$mockApiServer->stop();
        @unlink('logs/mock_config.json');
        parent::tearDownAfterClass();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->httpClient = new Client(['base_uri' => self::$appServer->getUrl(), 'http_errors' => false]);
        
        // Setup base data
        if (User::count() === 0) {
            User::create([
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'email' => 'test@example.com'
            ]);
        }
        Platform::updateOrCreate(['nome' => 'tumblr'], ['ativo' => true]);

        $user = User::first();
        $this->token = \Firebase\JWT\JWT::encode(
            ['sub' => $user->id, 'username' => $user->username],
            $_ENV['JWT_SECRET'],
            'HS256'
        );
    }

    private function setMockSyncResponse(int $status, array $body) {
        file_put_contents(
            'logs/mock_config.json',
            json_encode(['statusCode' => $status, 'body' => $body])
        );
    }

    public function testPublishSinglePlatformSuccess() {
        $this->setMockSyncResponse(200, ['message' => 'Published to Tumblr Successfully']);

        $payload = [
            'text' => 'Teste de Integração Real',
            'tags' => ['integration', 'test'],
            'platformOptions' => [
                'tumblr' => ['blogName' => 'test-blog']
            ]
        ];

        $response = $this->httpClient->post('/api/publish/tumblr', [
            'headers' => [
                'X-API-KEY' => $_ENV['API_ACCESS_TOKEN'],
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => $payload
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('message', $body);
        $this->assertStringContainsString('published', strtolower($body['message'] ?? ''));
        
        $postId = $body['post_id'] ?? null;
        $this->assertNotNull($postId);
        
        $post = Post::with(['tags', 'sends.platform'])->find($postId);
        $this->assertNotNull($post);
        $this->assertEquals('Teste de Integração Real', $post->texto);
        $this->assertCount(2, $post->tags);
        $this->assertEquals('tumblr', $post->sends[0]->platform->nome);
        $this->assertEquals(1, $post->sends[0]->sucesso);
    }

    public function testPublishSinglePlatformFailure() {
        $this->setMockSyncResponse(500, ['error' => 'Internal Server Error in Sync']);

        $payload = [
            'text' => 'Teste com Falha',
            'platformOptions' => ['tumblr' => ['blogName' => 'test-blog']]
        ];

        $response = $this->httpClient->post('/api/publish/tumblr', [
            'headers' => [
                'X-API-KEY' => $_ENV['API_ACCESS_TOKEN'],
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => $payload
        ]);

        $this->assertEquals(500, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $postId = $body['post_id'] ?? null;
        
        $post = Post::where('texto', 'Teste com Falha')->first();
        $this->assertNotNull($post);
        $send = $post->sends()->first();
        $this->assertEquals(0, $send->sucesso);
        $this->assertStringContainsString('Internal Server Error', $send->erro);
    }
}
