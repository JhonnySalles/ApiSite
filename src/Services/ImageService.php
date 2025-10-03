<?php

namespace ApiSite\Services;

use Supabase\CreateClient;

class ImageService {
  private $supabase;
  private $bucketName = 'SitePost';

  public function __construct() {
    $this->supabase = new CreateClient($_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']);
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

    foreach ($imagesPayload as $image) {
      if (!empty($image['url'])) {
        $finalUrls[] = $image['url'];
        continue;
      }

      if (empty($image['base64']))
        continue;

      // Ex: "data:image/png;base64,iVBORw0KGgo..."
      list($type, $data) = explode(';', $image['base64']);
      list(, $data) = explode(',', $data);
      $decodedData = base64_decode($data);

      $mimeType = explode(':', $type)[1];
      $extension = explode('/', $mimeType)[1];

      $fileName = 'uploads/' . uniqid() . '_' . time() . '.' . $extension;

      $response = $this->supabase->storage()->from($this->bucketName)->upload($fileName, $decodedData, ['contentType' => $mimeType, 'cacheControl' => '3600', 'upsert' => false]);

      if (isset($response['error'])) {
        LogService::getInstance()->error('Falha no upload para o Supabase', ['error' => $response['error']]);
        throw new \Exception('Não foi possível fazer o upload da imagem.');
      }

      $publicUrlData = $this->supabase->storage()->from($this->bucketName)->getPublicUrl($fileName);
      $finalUrls[] = $publicUrlData['publicUrl'];
    }

    return $finalUrls;
  }
}