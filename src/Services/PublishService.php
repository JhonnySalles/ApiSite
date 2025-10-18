<?php

namespace ApiSite\Services;

use ApiSite\Models\Platform;
use ApiSite\Models\Post;
use ApiSite\Models\Send;
use ApiSite\Models\Tag;
use DateTime;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class PublishService {

  private $imageService;

  public function __construct() {
    $this->imageService = new ImageService();
  }

  /**
   * Helper privado para traduzir 'x' para 'twitter'.
   */
  private function resolvePlatformAlias(string $name): string {
    return strtolower($name) === 'x' ? 'twitter' : $name;
  }

  /**
   * Cria uma nova postagem (do tipo 'POST') no banco de dados, incluindo suas imagens e registros de envio.
   * Orquestra toda a lógica de negócio para a criação de um Post.
   *
   * @param array $payload Os dados validados do Post.
   * @return Post O objeto do Postagem que foi criada.
   * @throws Exception Se ocorrer um erro durante a transação.
   */
  public function savePosts(array $payload): Post {
    if (!empty($payload['platforms']))
      $payload['platforms'] = array_map([$this, 'resolvePlatformAlias'], $payload['platforms']);

    $imageUrls = $this->imageService->processAndUploadImages($payload['images'] ?? null);

    DB::connection()->beginTransaction();

    try {
      $post = Post::updateOrCreate(['id' => $payload['id'] ?? null], ['tipo' => 'POST', 'situacao' => $payload['situacao'] ?? 'PENDENTE', 'texto' => $payload['text'] ?? null, 'tags' => $payload['tags'] ?? null, 'opcoes_plataforma' => $payload['platformOptions'] ?? null, 'callback_url' => $payload['callbackUrl'] ?? null, 'data_postagem' => new DateTime(),]);

      $post->images()->delete();
      if (!empty($imageUrls)) {
        $imageData = array_map(fn($url) => ['url' => $url], $imageUrls);
        $post->images()->createMany($imageData);
      }

      $post->sends()->delete();
      $platformsDB = Platform::whereIn('nome', $payload['platforms'])->get()->keyBy('nome');
      foreach ($payload['platforms'] as $platformName) {
        if (isset($platformsDB[$platformName]))
          Send::create(['postagem_id' => $post->id, 'plataforma_id' => $platformsDB[$platformName]->id,]);
      }

      DB::connection()->commit();
      return $post->load(['images', 'sends']);

    } catch (Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Cria um Post para uma única plataforma.
   *
   * @param string $platformName O nome da plataforma (ex: 'tumblr').
   * @param array $payload Os dados do Post (texto, imagens, etc.).
   * @return Post O objeto do Postagem criada.
   * @throws InvalidArgumentException Se a plataforma não for encontrada ou estiver inativa.
   * @throws Exception Se ocorrer outro erro durante a transação no banco de dados.
   */
  public function savePost(string $platformName, array $payload): Post {
    $platformName = $this->resolvePlatformAlias($platformName);

    $platform = Platform::where('nome', $platformName)->where('ativa', true)->first();
    if (!$platform)
      throw new InvalidArgumentException("Plataforma '$platformName' não encontrada ou está inativa.");

    $imageUrls = $this->imageService->processAndUploadImages($payload['images'] ?? null);

    $tagIds = [];
    if (!empty($payload['tags'])) {
      foreach ($payload['tags'] as $tagName) {
        $tag = Tag::firstOrCreate(['tag' => trim($tagName)]);
        $tagIds[] = $tag->id;
      }
    }

    DB::connection()->beginTransaction();

    try {
      $post = Post::updateOrCreate(['id' => $payload['id'] ?? null], ['tipo' => 'POST', 'situacao' => $payload['situacao'] ?? 'PENDENTE', 'texto' => $payload['text'] ?? null, 'opcoes_plataforma' => $payload['platformOptions'] ?? null, 'data_postagem' => new DateTime(),]);

      $post->images()->delete();
      if (!empty($imageUrls)) {
        $imageData = array_map(fn($url) => ['url' => $url], $imageUrls);
        $post->images()->createMany($imageData);
      }

      if (!empty($tagIds))
        $post->tags()->sync($tagIds);

      $post->sends()->delete();
      Send::create(['postagem_id' => $post->id, 'plataforma_id' => $platform->id,]);

      DB::connection()->commit();
      return $post->load(['images', 'sends', 'tags']);

    } catch (\Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Atualiza a situação de um Postagem pelo ID.
   *
   * @param int $postId O ID do Postagem a ser atualizado.
   * @param string $newSituacao A nova situação para o Postagem (ex: 'ENVIADO', 'SUCESSO', 'ALERTA').
   * @return Post O objeto do Postagem atualizado.
   * @throws InvalidArgumentException Se a nova situação não for válida.
   * @throws ModelNotFoundException Se o Postagem não for encontrado.
   * @throws Exception Se ocorrer um erro durante a atualização no banco de dados.
   */
  public function updatePostSituation(int $postId, string $newSituacao): Post {
    $validSituacoes = ['PENDENTE', 'ENVIADO', 'SUCESSO', 'ALERTA', 'EXCLUIDO'];
    if (!in_array($newSituacao, $validSituacoes))
      throw new InvalidArgumentException("Situação '$newSituacao' inválida.");

    $post = Post::where('id', $postId)->firstOrFail();
    $post->situacao = $newSituacao;

    if (!$post->save())
      throw new Exception("Falha ao salvar a nova situação para o Postagem ID: $postId.");

    return $post;
  }

  /**
   * Busca um histórico paginado de todas as postagens (Posts) com situação diferente de EXCLUIDO.
   * Inclui os relacionamentos de envios e plataformas para cada Postagem.
   *
   * @param int $page A página atual a ser retornada.
   * @param int $size O número de itens por página.
   * @return LengthAwarePaginator O resultado paginado da coleção de Postagens.
   */
  public function getHistoryPaginated(int $page = 1, int $size = 10): LengthAwarePaginator {
    // Usamos with() para carregar os relacionamentos de forma otimizada (Eager Loading)
    // Isso evita o problema de N+1 queries.
    // Trazemos cada postagem com seus envios, e para cada envio, sua plataforma.
    return Post::with(['sends.platform', 'images'])->where('situacao', '!=', 'EXCLUIDO')->orderBy('created_at', 'desc')->paginate($perPage = $size, $columns = ['*'], $pageName = 'page', $page = $page);
  }


  /**
   * Cria um único rascunho (Draft) no banco de dados.
   *
   * @param array $payload Os dados validados do Rascunho.
   * @return Post O objeto do Rascunho (Postagem) que foi criado.
   * @throws Exception Se ocorrer um erro durante a criação.
   */
  public function saveDraft(array $payload): Post {
    $imageUrls = $this->imageService->processAndUploadImages($payload['images'] ?? null);

    $tagIds = [];
    if (!empty($payload['tags'])) {
      foreach ($payload['tags'] as $tagName) {
        $tag = Tag::firstOrCreate(['tag' => trim($tagName)]);
        $tagIds[] = $tag->id;
      }
    }

    DB::connection()->beginTransaction();
    try {
      $draft = Post::updateOrCreate(['id' => $payload['id'] ?? null], ['tipo' => 'RASCUNHO', 'situacao' => $payload['situacao'] ?? 'PENDENTE', 'texto' => $payload['text'] ?? null, 'opcoes_plataforma' => $payload['platformOptions'] ?? null, 'data_postagem' => new DateTime(),]);

      $draft->images()->delete();
      if (!empty($imageUrls)) {
        $imageData = array_map(fn($url) => ['url' => $url], $imageUrls);
        $draft->images()->createMany($imageData);
      }

      if (!empty($tagIds))
        $draft->tags()->sync($tagIds);

      DB::connection()->commit();
      return $draft->load('images', 'tags');

    } catch (Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Cria múltiplos rascunhos (Drafts) em uma única transação no banco de dados.
   *
   * @param array $draftsPayload Um array contendo os payloads de cada rascunho a ser criado.
   * @return bool Retorna true se todos os rascunhos foram salvos com sucesso.
   * @throws Exception Se ocorrer um erro durante a transação, a transação será revertida.
   */
  public function saveDrafts(array $draftsPayload): bool {
    DB::connection()->beginTransaction();
    try {
      foreach ($draftsPayload as $payload)
        $this->saveDraft($payload);

      DB::connection()->commit();
      return true;
    } catch (\Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Busca todos os rascunhos (Drafts) que não foram excluídos, de forma paginada.
   *
   * @param int $page A página atual a ser retornada.
   * @param int $size O número de itens por página.
   * @return LengthAwarePaginator O resultado paginado da coleção de Rascunhos (Postagens).
   */
  public function getDraftsPaginated(int $page = 1, int $size = 10): LengthAwarePaginator {
    return Post::with('images')->where('tipo', 'RASCUNHO')->where('situacao', '!=', 'EXCLUIDO')->orderBy('created_at', 'desc')->paginate($size, ['*'], 'page', $page);
  }

  /**
   * Busca um rascunho (Draft) específico pelo ID.
   * O rascunho deve ter o tipo 'RASCUNHO' e não pode ter situação 'EXCLUIDO'.
   *
   * @param int $id O ID do rascunho a ser buscado.
   * @return Post O objeto do Rascunho (Postagem) encontrado.
   * @throws ModelNotFoundException Se o rascunho não for encontrado ou não atender às condições.
   */
  public function getDraft(int $id): ?Post {
    return Post::with('images')->where('id', $id)->where('tipo', 'RASCUNHO')->where('situacao', '!=', 'EXCLUIDO')->firstOrFail();
  }

  /**
   * Realiza a exclusão lógica (soft delete) de um rascunho (Draft) pelo ID.
   * A situação do rascunho será alterada para 'EXCLUIDO'.
   *
   * @param int $id O ID do rascunho a ser excluído.
   * @return bool Retorna true se o rascunho foi marcado como excluído com sucesso.
   * @throws ModelNotFoundException Se o rascunho não for encontrado.
   */
  public function deleteDraft(int $id): bool {
    $draft = $this->getDraft($id);
    $draft->situacao = 'EXCLUIDO';
    return $draft->save();
  }

  /**
   * Realiza a consulta de todas as tags presente no banco.
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getTags() {
    return Tag::orderBy('tag')->get();
  }
}