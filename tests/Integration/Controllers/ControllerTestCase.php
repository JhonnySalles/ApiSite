<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\User;
use GuzzleHttp\Client;
use Tests\Support\HttpServer;
use Tests\TestCase;

abstract class ControllerTestCase extends TestCase {
    protected static $appServer;
    protected $httpClient;
    protected $token;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $appPort = (int)($_ENV['MOCK_SERVER_PORT'] ?? 8080);
        self::$appServer = new HttpServer('localhost', $appPort, 'public');

        try {
            self::$appServer->start();
        } catch (\Exception $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void {
        if (self::$appServer) self::$appServer->stop();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->httpClient = new Client(['base_uri' => self::$appServer->getUrl(), 'http_errors' => false]);
        
        // Setup base user for tokens if needed
        User::firstOrCreate(['username' => 'testuser'], [
            'nome' => 'Test User',
            'username' => 'testuser',
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $user = User::where('username', 'testuser')->first();
        $this->token = \Firebase\JWT\JWT::encode(
            ['sub' => $user->id, 'username' => $user->username, 'iat' => time(), 'exp' => time() + 3600],
            $_ENV['JWT_SECRET'],
            'HS256'
        );
    }

    protected function getAuthHeaders(): array {
        return [
            'X-API-KEY' => $_ENV['API_ACCESS_TOKEN'],
            'Authorization' => 'Bearer ' . $this->token
        ];
    }
}
