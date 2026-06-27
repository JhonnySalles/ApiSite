<?php

namespace ApiSite\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use InvalidArgumentException;
use GuzzleHttp\Client as HttpClient;

class ImageService {
  private $s3Client;
  private $bucketName = 'SitePost';
  private $url;
  private $httpClient;

  public function __construct(?S3Client $s3Client = null, ?HttpClient $httpClient = null) {
    $this->url = $_ENV['B2_CLOUD_PUBLIC_URL'] ?? '';
    if ($s3Client) {
      $this->s3Client = $s3Client;
    } else {
      $this->s3Client = new S3Client([
        'version' => 'latest', 
        'region' => $_ENV['B2_CLOUD_S3_REGION'] ?? 'us-east-1', 
        'endpoint' => $_ENV['B2_CLOUD_S3_ENDPOINT'] ?? null, 
        'use_path_style_endpoint' => true, 
        'credentials' => [
          'key' => $_ENV['B2_CLOUD_S3_ACCESS_KEY_ID'] ?? '', 
          'secret' => $_ENV['B2_CLOUD_S3_SECRET_ACCESS_KEY'] ?? '',
        ],
      ]);
    }
    $this->httpClient = $httpClient ?? new HttpClient(['timeout' => 10.0]);
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
        $this->s3Client->putObject([
          'Bucket' => $this->bucketName,
          'Key' => $filePath,
          'Body' => $decodedData,
          'ContentType' => $mimeType
        ]);

        $publicUrl = rtrim($this->url, '/') . '/' . $this->bucketName . '/' . $filePath;
        $finalUrls[] = $publicUrl;

      } catch (\Exception $e) {
        LogService::getInstance()->error('Falha no upload para o B2 Cloud Storage', ['error' => $e->getMessage()]);
        throw new \Exception('Não foi possível fazer o upload da imagem.');
      }
    }

    return $finalUrls;
  }

  /**
   * Prepara um array de imagens para upload, garantindo que todas tenham base64 no formato Data URI.
   * Se uma imagem tem apenas URL, tenta baixá-la e convertê-la para base64.
   *
   * @param array|null $originalImagesPayload Array original vindo da requisição.
   * @return array Array de imagens prontas para upload, cada item com 'base64' e 'platforms'.
   * @throws InvalidArgumentException Se o base64 fornecido for inválido ou o download da URL falhar/não for imagem.
   */
  public function prepareImagesForUpload(?array $originalImagesPayload): array {
    if (empty($originalImagesPayload))
      return [];

    $preparedImages = [];

    foreach ($originalImagesPayload as $index => $image) {
      $validBase64DataUri = null;
      $platforms = $image['platforms'] ?? null;

      if (!empty($image['base64'])) {
        $pattern = '/^data:(image\/(?:png|jpeg|gif|webp));base64,(.*)$/';
        if (preg_match($pattern, $image['base64']))
          $validBase64DataUri = $image['base64']; else
          throw new InvalidArgumentException("A imagem (base64) na posição $index está com formato inválido. Esperado: 'data:image/jpeg;base64,...'");
      }

      if ($validBase64DataUri === null && !empty($image['url'])) {
        LogService::getInstance()->info("Baixando imagem da URL para base64.", ['url' => $image['url']]);
        try {
          $response = $this->httpClient->get($image['url']);

          if ($response->getStatusCode() === 200) {
            $imageData = $response->getBody()->getContents();
            $mimeType = $response->getHeaderLine('Content-Type');

            if (strpos($mimeType, 'image/') === 0 && in_array(explode('/', $mimeType)[1], ['png', 'jpeg', 'gif', 'webp'])) {
              $base64Encoded = base64_encode($imageData);
              $validBase64DataUri = 'data:' . $mimeType . ';base64,' . $base64Encoded;
            } else
              throw new InvalidArgumentException("A URL na posição $index não aponta para um tipo de imagem suportado (tipo: $mimeType).");

          } else
            throw new InvalidArgumentException("Falha ao baixar imagem da URL na posição $index (Status: {$response->getStatusCode()}).");

        } catch (\Exception $e) {
          LogService::getInstance()->error("Erro ao baixar/processar imagem da URL", ['url' => $image['url'], 'error' => $e->getMessage()]);
          throw new InvalidArgumentException("Não foi possível processar a imagem da URL na posição $index: " . $e->getMessage());
        }
      }

      if ($validBase64DataUri !== null)
        $preparedImages[] = ['base64' => $validBase64DataUri, 'platforms' => $platforms];
      else
        LogService::getInstance()->warning("Imagem na posição $index foi ignorada (sem base64 válido ou URL funcional).");
    }

    return $preparedImages;
  }

  /**
   * Baixa as imagens privadas do B2 Cloud e retorna como Base64 Data URI.
   *
   * @param array $links Array de URLs originais armazenadas.
   * @return array Array mapeando [url_original => base64_data_uri]
   */
  public function downloadImagesAsBase64(array $links): array {
    $results = [];
    foreach ($links as $link) {
      if (empty($link)) continue;

      try {
        $key = $this->extractKeyFromUrl($link);
        $result = $this->s3Client->getObject([
          'Bucket' => $this->bucketName,
          'Key'    => $key
        ]);

        $body = $result['Body']->getContents();
        $mimeType = $result['ContentType'] ?? 'image/jpeg';

        $results[$link] = 'data:' . $mimeType . ';base64,' . base64_encode($body);
      } catch (\Exception $e) {
        LogService::getInstance()->error('Erro ao baixar imagem como Base64 do B2 Cloud', [
          'link' => $link,
          'error' => $e->getMessage()
        ]);
        $results[$link] = null;
      }
    }
    return $results;
  }

  /**
   * Gera URLs pré-assinadas temporárias (Presigned URLs) para acesso direto às imagens privadas.
   *
   * @param array $links Array de URLs originais armazenadas.
   * @return array Array mapeando [url_original => presigned_url]
   */
  public function downloadImagesAsUrls(array $links): array {
    $results = [];
    foreach ($links as $link) {
      if (empty($link)) continue;

      try {
        $key = $this->extractKeyFromUrl($link);
        $command = $this->s3Client->getCommand('GetObject', [
          'Bucket' => $this->bucketName,
          'Key'    => $key
        ]);

        $request = $this->s3Client->createPresignedRequest($command, '+1 hour');
        $presignedUrl = (string) $request->getUri();

        $results[$link] = $presignedUrl;
      } catch (\Exception $e) {
        LogService::getInstance()->error('Erro ao gerar URL temporaria no B2 Cloud', [
          'link' => $link,
          'error' => $e->getMessage()
        ]);
        $results[$link] = null;
      }
    }
    return $results;
  }

  /**
   * Extrai a Key do arquivo a partir de uma URL armazenada.
   *
   * @param string $url URL completa da imagem.
   * @return string A Key/Caminho do objeto no B2.
   */
  private function extractKeyFromUrl(string $url): string {
    $bucketWithSlashes = '/' . $this->bucketName . '/';
    $pos = strpos($url, $bucketWithSlashes);
    if ($pos !== false) {
      return substr($url, $pos + strlen($bucketWithSlashes));
    }
    return basename(parse_url($url, PHP_URL_PATH));
  }

  /**
   * Coleta todas as URLs das imagens dos posts fornecidos, gera URLs pré-assinadas
   * e as anexa como o atributo dinâmico 'url_assinado' em cada modelo de imagem.
   *
   * @param mixed $posts Um post único ou uma coleção/array de posts.
   * @return mixed Os posts com as URLs assinadas anexadas às suas imagens.
   */
  public function appendPresignedUrlsToPosts($posts) {
    $urls = [];
    $isSingle = $posts instanceof \ApiSite\Models\Post;
    $iterablePosts = $isSingle ? [$posts] : $posts;

    foreach ($iterablePosts as $post) {
      if ($post->relationLoaded('images')) {
        foreach ($post->images as $image) {
          if (!empty($image->url)) {
            $urls[] = $image->url;
          }
        }
      }
    }

    if (empty($urls)) {
      return $posts;
    }

    $urlsMap = $this->downloadImagesAsUrls(array_unique($urls));

    foreach ($iterablePosts as $post) {
      if ($post->relationLoaded('images')) {
        foreach ($post->images as $image) {
          if (!empty($image->url)) {
            $image->setAttribute('url_assinado', $urlsMap[$image->url] ?? null);
          }
        }
      }
    }

    return $posts;
  }

}