<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\User;

class AuthControllerIntegrationTest extends ControllerTestCase {
    public function testLoginSuccess() {
        // O usuário de teste já é criado no setUp do ControllerTestCase
        $payload = [
            'username' => 'testuser',
            'password' => 'password123'
        ];

        $response = $this->httpClient->post('/auth/login', [
            'headers' => ['X-API-KEY' => $_ENV['API_ACCESS_TOKEN']],
            'json' => $payload
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('access_token', $body['authorisation']);
        $this->assertArrayHasKey('refresh_token', $body['authorisation']);
    }

    public function testLoginInvalidCredentials() {
        $payload = [
            'username' => 'testuser',
            'password' => 'wrongpassword'
        ];

        $response = $this->httpClient->post('/auth/login', [
            'headers' => ['X-API-KEY' => $_ENV['API_ACCESS_TOKEN']],
            'json' => $payload
        ]);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Credenciais inválidas.', $body['message']);
    }

    public function testLoginMissingFields() {
        $payload = ['username' => 'testuser'];

        $response = $this->httpClient->post('/auth/login', [
            'headers' => ['X-API-KEY' => $_ENV['API_ACCESS_TOKEN']],
            'json' => $payload
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testLoginInvalidApiKey() {
        $payload = [
            'username' => 'testuser',
            'password' => 'password123'
        ];

        $response = $this->httpClient->post('/api/auth/login', [
            'headers' => ['X-API-KEY' => 'invalid_key'],
            'json' => $payload
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }
}
