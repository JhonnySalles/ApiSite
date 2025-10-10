<?php

namespace ApiSite\Services;

use Aws\S3\S3Client;
use InvalidArgumentException;

class ImageService {
  private $supabase;
  private $bucketName = 'SitePost';
  private $url;

  public function __construct() {
    $this->url = $_ENV['SUPABASE_URL'];
    $this->supabase = new S3Client(['version' => 'latest', 'region' => $_ENV['SUPABASE_S3_REGION'], 'endpoint' => $_ENV['SUPABASE_S3_ENDPOINT'], 'use_path_style_endpoint' => true, 'credentials' => ['key' => $_ENV['SUPABASE_S3_ACCESS_KEY_ID'], 'secret' => $_ENV['SUPABASE_S3_SECRET_ACCESS_KEY'],],]);
  }

  /**
   * Processa um array de imagens. Se a imagem tiver base64, faz o upload. Se já tiver URL, a mantém.
   *
   * @param array|null $imagesPayload Array de imagens, cada uma contendo 'base64' ou 'url'.
   * @return array Array de URLs finais das imagens.
   * @throws \Exception Se o upload falhar.
   */
  public function processAndUploadImages(?array $imagesPayload): array {
    if (empty($imagesPayload))
      return [];

    $finalUrls = [];

    foreach ($imagesPayload as $index => $image) {
      if (!empty($image['url'])) {
        $finalUrls[] = $image['url'];
        continue;
      }

      if (empty($image['base64']))
        continue;

      $pattern = '/^data:(image\/(?:png|jpeg|gif|webp));base64,(.*)$/';
      if (!preg_match($pattern, $image['base64'], $matches))
        throw new InvalidArgumentException("A imagem na posição $index está com um formato de base64 inválido. O formato esperado é 'data:image/jpeg;base64,...'");


      $mimeType = $matches[1];
      $base64Data = $matches[2];
      $extension = explode('/', $mimeType)[1];
      $decodedData = base64_decode($base64Data);

      $filePath = uniqid('img_', true) . '.' . $extension;

      try {
        $this->supabase->putObject([
          'Bucket' => $this->bucketName,
          'Key'    => $filePath,
          'Body'   => $decodedData,
          'ContentType' => $mimeType,
          'ACL'    => 'public-read',
        ]);

        $publicUrl = $this->url . '/storage/v1/object/public/' . $this->bucketName . '/' . $filePath;
        $finalUrls[] = $publicUrl;

      } catch (RequestException $e) {
        $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        LogService::getInstance()->error('Falha no upload para o Supabase via Guzzle', ['error' => $responseBody]);
        throw new \Exception('Não foi possível fazer o upload da imagem.');
      }
    }

    return $finalUrls;
  }
}