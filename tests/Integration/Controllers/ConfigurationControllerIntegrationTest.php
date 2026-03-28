<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\Platform;

class ConfigurationControllerIntegrationTest extends ControllerTestCase {
    public function testGetPlatformsReturnsList() {
        // Garante que existe ao menos uma plataforma
        if (Platform::where('nome', 'tumblr')->count() === 0) {
            Platform::create(['nome' => 'tumblr', 'ativo' => true]);
        }

        $response = $this->httpClient->get('/api/configuration/platforms', [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertIsArray($body);
        $this->assertGreaterThanOrEqual(1, count($body));
        $this->assertEquals('tumblr', $body[0]['nome']);
    }

    public function testUpdatePlatformStatus() {
        $platform = Platform::where('nome', 'tumblr')->first();
        if (!$platform) {
            $platform = Platform::create(['nome' => 'tumblr', 'ativo' => true]);
        }

        $payload = ['ativo' => false];

        $response = $this->httpClient->put("/api/configuration/platforms/tumblr", [
            'headers' => $this->getAuthHeaders(),
            'json' => $payload
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        
        $platform->refresh();
        $this->assertEquals(0, $platform->ativo);
    }

    public function testGetOnePlatformNotFound() {
        $response = $this->httpClient->get("/api/configuration/platforms/nonexistent", [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
