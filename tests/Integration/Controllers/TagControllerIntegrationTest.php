<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\Tag;

class TagControllerIntegrationTest extends ControllerTestCase {
    public function testGetTagsReturnsList() {
        // Popula banco com tags de teste com segurança contra duplicados
        Tag::firstOrCreate(['tag' => 'php']);
        Tag::firstOrCreate(['tag' => 'testing']);

        $response = $this->httpClient->get('/api/tags', [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertIsArray($body);
        $this->assertContains('php', $body);
        $this->assertContains('testing', $body);
    }

    public function testGetTagsUnauthorized() {
        $response = $this->httpClient->get('/api/tags');
        $this->assertEquals(403, $response->getStatusCode()); // X-API-KEY missing
    }
}
