<?php

namespace Tests\Unit\Services;

use ApiSite\Models\Platform;
use ApiSite\Models\Post;
use ApiSite\Models\Send;
use ApiSite\Services\ImageService;
use ApiSite\Services\PublishService;
use Tests\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class PublishServiceTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        if (Platform::count() === 0) {
            Platform::create(['nome' => 'tumblr', 'ativo' => true]);
            Platform::create(['nome' => 'twitter', 'ativo' => true]);
        }
    }

    public function testSavePostsSuccess() {
        $imageServiceMock = Mockery::mock(ImageService::class);
        $imageServiceMock->shouldReceive('processAndUploadImages')
            ->once()
            ->andReturn(['http://test.url/img1.jpg']);

        $service = new PublishService($imageServiceMock);

        $payload = [
            'platforms' => ['tumblr'],
            'text' => 'Test Post Content',
            'tags' => ['tag1', 'tag2'],
            'images' => [
                ['base64' => 'something']
            ]
        ];

        $post = $service->savePosts($payload);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals('Test Post Content', $post->texto);
        $this->assertCount(1, $post->images);
        $this->assertCount(1, $post->sends);
        
        $send = $post->sends->first();
        $this->assertNotNull($send->plataforma_id);
    }

    public function testSavePostSinglePlatform() {
        $imageServiceMock = Mockery::mock(ImageService::class);
        $imageServiceMock->shouldReceive('processAndUploadImages')
            ->once()
            ->andReturn([]);

        $service = new PublishService($imageServiceMock);

        $payload = [
            'text' => 'Single Platform Post',
            'platforms' => ['twitter']
        ];

        $post = $service->savePost('twitter', $payload);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertCount(1, $post->sends);
        $this->assertEquals('twitter', $post->sends->first()->platform->nome);
    }
}
