<?php

namespace ApiSite\Services;

use ApiSite\Models\Image;
use ApiSite\Models\Platform;
use ApiSite\Models\Post;
use ApiSite\Models\Send;
use DateTime;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Capsule\Manager as DB;

class PublishService {
  /**
   * Cria uma nova postagem, suas imagens e seus envios no banco de dados.
   * Orquestra toda a lógica de negócio para a criação de uma postagem.
   *
   * @param array $payload Os dados validados da postagem.
   * @return Postagem O objeto da postagem que foi criada.
   * @throws Exception Se ocorrer um erro durante a transação.
   */
  public function savePosts(array $payload): Post {
    DB::connection()->beginTransaction();

    try {
      $postagem = Post::create(['texto' => $payload['text'] ?? null, 'tags' => $payload['tags'] ?? null, 'opcoes_plataforma' => $payload['platformOptions'] ?? null, 'callback_url' => $payload['callbackUrl'] ?? null, 'data_postagem' => $payload['scheduleDate'] ?? new DateTime(),]);

      if (!empty($payload['images'])) {
        foreach ($payload['images'] as $imagePayload) {
          Image::create(['postagem_id' => $postagem->id, 'base64' => $imagePayload['base64'], 'plataformas' => $imagePayload['platforms'] ?? null,]);
        }
      }

      $platformsDB = Platform::whereIn('nome', $payload['platforms'])->get()->keyBy('nome');
      foreach ($payload['platforms'] as $platform) {
        if (isset($platformsDB[$platform]))
          Send::create(['postagem_id' => $postagem->id, 'plataforma_id' => $platformsDB[$platform]->id,]);
      }

      DB::connection()->commit();
      return $postagem;

    } catch (Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Cria uma postagem para uma única plataforma.
   *
   * @param string $platform O nome da plataforma (ex: 'tumblr').
   * @param array $payload Os dados da postagem (texto, imagens, etc.).
   * @return Post O objeto da postagem criada.
   * @throws InvalidArgumentException Se a plataforma não for encontrada.
   * @throws \Exception Se ocorrer outro erro no banco.
   */
  public function savePost(string $platform, array $payload): Post {
    $plat = Platform::where('nome', $platform)->first();

    if (!$plat)
      throw new InvalidArgumentException("Plataforma '$platform' não encontrada.");

    DB::connection()->beginTransaction();

    try {
      $post = Post::create(['texto' => $payload['text'] ?? null, 'tags' => $payload['tags'] ?? null, 'opcoes_plataforma' => $payload['platformOptions'] ?? null, 'data_postagem' => new DateTime(),]);

      if (!empty($payload['images'])) {
        foreach ($payload['images'] as $base64Image)
          Image::create(['postagem_id' => $post->id, 'base64' => $base64Image,]);
      }

      Send::create(['postagem_id' => $post->id, 'plataforma_id' => $plat->id,]);

      DB::connection()->commit();

      return $post;
    } catch (\Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Busca um histórico paginado de todas as postagens.
   *
   * @param int $page A página atual.
   * @param int $size O número de itens por página.
   * @return LengthAwarePaginator O resultado paginado do Eloquent.
   */
  public function getHistoryPaginated(int $page = 1, int $size = 10): LengthAwarePaginator {
    // Usamos with() para carregar os relacionamentos de forma otimizada (Eager Loading)
    // Isso evita o problema de N+1 queries.
    // Trazemos cada postagem com seus envios, e para cada envio, sua plataforma.
    return Post::with(['envios.plataforma', 'imagens'])->orderBy('created_at', 'desc')->paginate($perPage = $size, $columns = ['*'], $pageName = 'page', $page = $page);
  }
}