<?php

namespace Tests\Integration\Controllers;

class DocsControllerIntegrationTest extends ControllerTestCase {
    public function testSwaggerJsonReturnsOpenApiSpec() {
        $response = $this->httpClient->get('/docs/api-spec');
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('openapi', $body);
        $this->assertEquals('3.0.0', $body['openapi']);
        $this->assertArrayHasKey('info', $body);
        $this->assertEquals('API de Postagem em Redes Sociais', $body['info']['title']);
    }

    public function testSwaggerUiReturnsHtml() {
        $response = $this->httpClient->get('/docs');
        
        $this->assertEquals(200, $response->getStatusCode());
        $html = (string)$response->getBody();
        
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('/docs/api-spec', $html);
    }
}
