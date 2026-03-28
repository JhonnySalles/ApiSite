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

        $service = new ImageService($s3Mock);

        $imagesPayload = [
            [
                'base64' => 'data:image/jpeg;base64,' . base64_encode('test image content')
            ]
        ];

        $urls = $service->processAndUploadImages($imagesPayload);

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('https://test.supabase.co/storage/v1/object/public/SitePost/img_', $urls[0]);
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
}
