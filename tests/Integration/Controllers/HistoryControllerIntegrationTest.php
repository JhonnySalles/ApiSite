<?php

namespace Tests\Integration\Controllers;

use ApiSite\Models\Post;

class HistoryControllerIntegrationTest extends ControllerTestCase {
    public function testGetHistoryReturnsPaginatedData() {
        // Cria alguns posts no banco
        Post::create([
            'texto' => 'Post de Histórico 1',
            'tipo' => 'POST',
            'situacao' => 'SUCESSO',
            'data_postagem' => date('Y-m-d H:i:s')
        ]);
        Post::create([
            'texto' => 'Post de Histórico 2',
            'tipo' => 'POST',
            'situacao' => 'PENDENTE',
            'data_postagem' => date('Y-m-d H:i:s')
        ]);

        $response = $this->httpClient->get('/api/history', [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('data', $body);
        $texts = array_column($body['data'], 'text');
        $this->assertContains('Post de Histórico 1', $texts);
        $this->assertContains('Post de Histórico 2', $texts);
    }

    public function testDeleteHistorySuccess() {
        $post = Post::create([
            'texto' => 'Post para Deletar',
            'tipo' => 'POST',
            'situacao' => 'SUCESSO',
            'data_postagem' => date('Y-m-d H:i:s')
        ]);

        $response = $this->httpClient->delete("/api/history/{$post->id}", [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(204, $response->getStatusCode());
        
        // Verifica se foi marcado como EXCLUIDO no banco
        $post->refresh();
        $this->assertEquals('EXCLUIDO', $post->situacao);
    }

    public function testDeleteHistoryNotFound() {
        $response = $this->httpClient->delete("/api/history/99999", [
            'headers' => $this->getAuthHeaders()
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
