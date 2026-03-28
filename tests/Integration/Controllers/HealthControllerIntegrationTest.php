<?php

namespace Tests\Integration\Controllers;

class HealthControllerIntegrationTest extends ControllerTestCase {
    public function testHealthCheckReturnsSuccess() {
        $response = $this->httpClient->get('/health');
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('message', $body);
        $this->assertStringContainsString('A API está funcionando', $body['message']);
    }

    public function testSentryErrorEndpointReturns500() {
        $response = $this->httpClient->get('/test-sentry');
        
        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('message', $body);
        $this->assertEquals('Ocorreu um erro interno no servidor.', $body['message']);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('erro de teste', $body['error']);
    }
}
