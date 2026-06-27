<?php

namespace Tests\Unit\Services;

use ApiSite\Services\ImageService;
use Aws\S3\S3Client;
use GuzzleHttp\Client as HttpClient;
use Tests\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class ImageServiceTest extends TestCase {
    use MockeryPHPUnitIntegration;

    public function testProcessAndUploadImagesWithBase64() {
        $s3Mock = Mockery::mock(S3Client::class);
        $s3Mock->shouldReceive('putObject')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['Bucket'] === 'SitePost' && 
                       strpos($args['Key'], 'img_') === 0 && 
                       $args['ContentType'] === 'image/jpeg';
            }))
            ->andReturn(new \Aws\Result([]));

        $_ENV['B2_CLOUD_PUBLIC_URL'] = 'https://test.b2cloud.co';
        $service = new ImageService($s3Mock);

        $imagesPayload = [
            [
                'base64' => 'data:image/jpeg;base64,' . base64_encode('test image content')
            ]
        ];

        $urls = $service->processAndUploadImages($imagesPayload);

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('https://test.b2cloud.co/SitePost/img_', $urls[0]);
    }

    public function testProcessAndUploadImagesWithExistingUrl() {
        $s3Mock = Mockery::mock(S3Client::class);
        $s3Mock->shouldNotReceive('putObject');

        $service = new ImageService($s3Mock);

        $imagesPayload = [
            [
                'url' => 'http://example.com/existing.png'
            ]
        ];

        $urls = $service->processAndUploadImages($imagesPayload);

        $this->assertCount(1, $urls);
        $this->assertEquals('http://example.com/existing.png', $urls[0]);
    }

    public function testDownloadImagesAsBase64() {
        $s3Mock = Mockery::mock(S3Client::class);
        $streamMock = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $streamMock->shouldReceive('getContents')
            ->once()
            ->andReturn('raw image data');

        $s3Mock->shouldReceive('getObject')
            ->once()
            ->with([
                'Bucket' => 'SitePost',
                'Key' => 'img_123.jpg'
            ])
            ->andReturn(new \Aws\Result([
                'Body' => $streamMock,
                'ContentType' => 'image/png'
            ]));

        $service = new ImageService($s3Mock);
        $results = $service->downloadImagesAsBase64(['https://test.b2cloud.co/SitePost/img_123.jpg']);

        $expectedBase64 = 'data:image/png;base64,' . base64_encode('raw image data');
        $this->assertEquals([
            'https://test.b2cloud.co/SitePost/img_123.jpg' => $expectedBase64
        ], $results);
    }

    public function testDownloadImagesAsUrls() {
        $s3Mock = Mockery::mock(S3Client::class);
        $commandMock = Mockery::mock(\Aws\CommandInterface::class);
        $requestMock = Mockery::mock(\Psr\Http\Message\RequestInterface::class);
        $uriMock = Mockery::mock(\Psr\Http\Message\UriInterface::class);

        $s3Mock->shouldReceive('getCommand')
            ->once()
            ->with('GetObject', [
                'Bucket' => 'SitePost',
                'Key' => 'img_123.jpg'
            ])
            ->andReturn($commandMock);

        $s3Mock->shouldReceive('createPresignedRequest')
            ->once()
            ->with($commandMock, '+1 hour')
            ->andReturn($requestMock);

        $requestMock->shouldReceive('getUri')
            ->once()
            ->andReturn($uriMock);

        $uriMock->shouldReceive('__toString')
            ->once()
            ->andReturn('https://test.b2cloud.co/SitePost/img_123.jpg?token=abc');

        $service = new ImageService($s3Mock);
        $results = $service->downloadImagesAsUrls(['https://test.b2cloud.co/SitePost/img_123.jpg']);

        $this->assertEquals([
            'https://test.b2cloud.co/SitePost/img_123.jpg' => 'https://test.b2cloud.co/SitePost/img_123.jpg?token=abc'
        ], $results);
    }

    public function testAppendPresignedUrlsToPosts() {
        $s3Mock = Mockery::mock(S3Client::class);
        $commandMock = Mockery::mock(\Aws\CommandInterface::class);
        $requestMock = Mockery::mock(\Psr\Http\Message\RequestInterface::class);
        $uriMock = Mockery::mock(\Psr\Http\Message\UriInterface::class);

        $s3Mock->shouldReceive('getCommand')
            ->once()
            ->with('GetObject', [
                'Bucket' => 'SitePost',
                'Key' => 'img_123.jpg'
            ])
            ->andReturn($commandMock);

        $s3Mock->shouldReceive('createPresignedRequest')
            ->once()
            ->with($commandMock, '+1 hour')
            ->andReturn($requestMock);

        $requestMock->shouldReceive('getUri')
            ->once()
            ->andReturn($uriMock);

        $uriMock->shouldReceive('__toString')
            ->once()
            ->andReturn('https://test.b2cloud.co/SitePost/img_123.jpg?token=abc');

        $image = new \ApiSite\Models\Image();
        $image->url = 'https://test.b2cloud.co/SitePost/img_123.jpg';

        $post = new \ApiSite\Models\Post();
        $post->setRelation('images', collect([$image]));

        $service = new ImageService($s3Mock);
        $service->appendPresignedUrlsToPosts($post);

        $this->assertEquals('https://test.b2cloud.co/SitePost/img_123.jpg?token=abc', $image->url_assinado);
    }
}
