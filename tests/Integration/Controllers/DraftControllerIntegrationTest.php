<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\Post;

class DraftControllerIntegrationTest extends ControllerTestCase {
    public function testCreateDraftSuccess() {
        $payload = [
            'text' => 'Meu novo rascunho',
            'tags' => ['draft', 'php']
        ];

        $response = $this->httpClient->post('/api/draft', [
            'headers' => $this->getAuthHeaders(),
            'json' => $payload
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('id', $body);
        $this->assertEquals('Meu novo rascunho', $body['texto']);
        $this->assertEquals('PENDENTE', $body['situacao']);
    }

    public function testGetAllDrafts() {
        Post::create(['texto' => 'Rascunho DEBUG 1', 'tipo' => 'RASCUNHO', 'data_postagem' => date('Y-m-d H:i:s')]);
        Post::create(['texto' => 'Rascunho DEBUG 2', 'tipo' => 'RASCUNHO', 'data_postagem' => date('Y-m-d H:i:s')]);

        $response = $this->httpClient->get('/api/draft', [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('data', $body);
        $this->assertGreaterThanOrEqual(2, count($body['data']));
    }

    public function testGetOneDraft() {
        $draft = Post::create(['texto' => 'Rascunho Único', 'tipo' => 'RASCUNHO', 'data_postagem' => date('Y-m-d H:i:s')]);

        $response = $this->httpClient->get("/api/draft/{$draft->id}", [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Rascunho Único', $body['texto']);
    }

    public function testDeleteDraft() {
        $draft = Post::create(['texto' => 'Rascunho para Deletar', 'tipo' => 'RASCUNHO', 'data_postagem' => date('Y-m-d H:i:s')]);

        $response = $this->httpClient->delete("/api/draft/{$draft->id}", [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(204, $response->getStatusCode());
        
        $draft->refresh();
        $this->assertEquals('EXCLUIDO', $draft->situacao);
    }
}
