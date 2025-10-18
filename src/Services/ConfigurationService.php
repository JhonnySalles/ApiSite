<?php

namespace ApiSite\Services;

use ApiSite\Models\Platform;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ConfigurationService {

  /**
   * Helper privado para traduzir 'x' para 'twitter'.
   */
  private function resolvePlatformAlias(string $name): string {
    return strtolower($name) === 'x' ? 'twitter' : $name;
  }

  /**
   * Retorna uma coleção de todas as plataformas registradas no banco de dados.
   * Inclui os blogs associados a cada plataforma através de Eager Loading.
   *
   * @return Collection A coleção de objetos Platform.
   */
  public function getPlatforms() {
    return Platform::with('blogs')->orderBy('id')->get();
  }

  /**
   * Busca e retorna uma única plataforma pelo seu nome.
   *
   * @param string $name O nome único da plataforma a ser buscada (ex: 'tumblr').
   * @return Platform O objeto da plataforma encontrada.
   * @throws InvalidArgumentException Se nenhuma plataforma for encontrada com o nome especificado.
   */
  public function getPlatformByName(string $name) {
    $name = $this->resolvePlatformAlias($name);

    $platform = Platform::where('nome', $name)->first();
    if (!$platform)
      throw new InvalidArgumentException("Plataforma '$name' não encontrada.");

    return $platform->load('blogs');
  }

  /**
   * Atualiza o status (ativo/inativo) de múltiplas plataformas em uma única transação.
   *
   * @param array $payload Um array de plataformas, onde cada item é um array associativo com 'id' e 'ativo'. Ex: [['id' => 1, 'ativo' => false]]
   * @return bool Retorna true se a operação for bem-sucedida.
   * @throws \Exception Se ocorrer um erro durante a transação no banco de dados.
   */
  public function savePlatforms(array $payload): bool {
    DB::connection()->beginTransaction();
    try {
      foreach ($payload as $platformData) {
        if (!isset($platformData['nome']))
          continue;

        $platformName = $this->resolvePlatformAlias($platformData['nome']);
        $platform = Platform::where('nome', $platformName)->first();

        if ($platform)
          $this->updatePlatformData($platform, $platformData);
      }
      DB::connection()->commit();
      return true;
    } catch (\Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Atualiza o status (ativo/inativo) de uma única plataforma, identificada pelo nome.
   *
   * @param string $name O nome da plataforma a ser atualizada.
   * @param array $payload Um array associativo contendo a chave 'ativo'. Ex: ['ativo' => true]
   * @return Platform O objeto da plataforma com os dados atualizados.
   * @throws InvalidArgumentException Se a plataforma não for encontrada ou se a chave 'ativo' estiver ausente no payload.
   */
  public function savePlatform(string $name, array $payload): Platform {
    $name = $this->resolvePlatformAlias($name);
    $platform = $this->getPlatformByName($name);
    return $this->updatePlatformData($platform, $payload);
  }

  /**
   * Helper privado para atualizar os dados de uma plataforma, incluindo a lógica do Tumblr.
   */
  private function updatePlatformData(Platform $platform, array $payload): Platform {
    DB::connection()->beginTransaction();
    try {
      if (isset($payload['ativo'])) {
        $platform->ativo = (bool)$payload['ativo'];
        $platform->save();
      }

      if ($platform->nome === 'tumblr' && isset($payload['blogs']))
        $this->updateTumblrBlogs($platform, $payload['blogs']);

      DB::connection()->commit();
      return $platform->load('blogs');
    } catch (\Exception $e) {
      DB::connection()->rollBack();
      throw $e;
    }
  }

  /**
   * Helper privado com a lógica de atualização dos blogs do Tumblr.
   */
  private function updateTumblrBlogs(Platform $platform, array $blogsPayload) {
    $selectedBlogData = null;
    foreach ($blogsPayload as $blogData) {
      if (!empty($blogData['selecionado'])) {
        $selectedBlogData = $blogData;
        break;
      }
    }

    if ($selectedBlogData === null)
      throw new InvalidArgumentException("Para a plataforma Tumblr, ao menos um blog deve ser enviado e marcado como 'selecionado'.");

    $platform->blogs()->update(['selecionado' => false]);

    $blogToSelect = null;
    if (!empty($selectedBlogData['id']))
      $blogToSelect = Blog::find($selectedBlogData['id']); elseif (!empty($selectedBlogData['nome']))
      $blogToSelect = $platform->blogs()->where('nome', $selectedBlogData['nome'])->first();

    if ($blogToSelect) {
      $blogToSelect->selecionado = true;
      $blogToSelect->save();
    } else
      throw new InvalidArgumentException("O blog '{$selectedBlogData['nome']}' a ser selecionado não foi encontrado.");
  }

  /**
   * Busca e retorna todos os blogs associados a uma plataforma específica.
   *
   * @param string $platform O nome da plataforma da qual os blogs serão buscados.
   * @return Collection A coleção de objetos Blog associados à plataforma.
   * @throws InvalidArgumentException Se a plataforma especificada não for encontrada.
   */
  public function getBlogsForPlatform(string $platform) {
    $platform = $this->resolvePlatformAlias($platform);
    $data = $this->getPlatformByName($platform);
    return $data->blogs;
  }
}