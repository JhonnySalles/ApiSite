<?php

namespace ApiSite\Services;

use ApiSite\Models\Blog;
use ApiSite\Models\Platform;
use Exception;
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

  /**
   * Substitui todos os blogs de uma plataforma específica pelos novos dados fornecidos.
   * Todos os blogs existentes para a plataforma serão excluídos antes da inserção.
   *
   * @param string $platformName Nome da plataforma (ex: 'tumblr').
   * @param array $externalBlogs Array de blogs obtidos da API externa. Ex: [['name' => ..., 'title' => ...]]
   * @return bool Retorna true se a substituição foi bem-sucedida.
   * @throws InvalidArgumentException Se a plataforma não for encontrada localmente.
   * @throws Exception Se ocorrer erro durante a transação no banco de dados.
   */
  public function saveBlogs(string $platformName, array $externalBlogs): bool {
    $platformName = $this->resolvePlatformAlias($platformName);

    $platform = Platform::where('nome', $platformName)->first();
    if (!$platform)
      throw new InvalidArgumentException("Plataforma '$platformName' não encontrada no banco de dados local para salvar blogs.");

    $currentBlogs = $platform->blogs()->get();
    $previouslySelectedBlogName = null;
    foreach ($currentBlogs as $blog) {
      if ($blog->selecionado) {
        $previouslySelectedBlogName = $blog->nome;
        LogService::getInstance()->info("Blog '{$previouslySelectedBlogName}' estava selecionado anteriormente para {$platformName}.");
        break;
      }
    }

    DB::connection()->beginTransaction();
    try {
      $deletedCount = $platform->blogs()->delete();
      LogService::getInstance()->info("Removidos {$deletedCount} blogs antigos para a plataforma {$platformName}.");

      $blogsToInsert = [];
      $now = new \DateTime();
      foreach ($externalBlogs as $extBlog) {
        if (empty($extBlog['name']) || empty($extBlog['title']))
          continue;

        $blogsToInsert[] = ['plataforma_id' => $platform->id, 'nome' => $extBlog['name'], 'titulo' => $extBlog['title'], 'selecionado' => false, 'created_at' => $now, 'updated_at' => $now,];
      }

      if (!empty($blogsToInsert)) {
        Blog::insert($blogsToInsert);
        LogService::getInstance()->info("Inseridos " . count($blogsToInsert) . " novos blogs para a plataforma {$platformName}.");
      }

      if ($previouslySelectedBlogName !== null) {
        $newlySelectedBlog = Blog::where('plataforma_id', $platform->id)->where('nome', $previouslySelectedBlogName)->first();

        if ($newlySelectedBlog) {
          $newlySelectedBlog->selecionado = true;
          $newlySelectedBlog->save();
          LogService::getInstance()->info("Blog '{$previouslySelectedBlogName}' foi re-selecionado para {$platformName}.");
        } else
          LogService::getInstance()->warning("Blog '{$previouslySelectedBlogName}' que estava selecionado não foi encontrado nos novos dados para {$platformName}. Nenhum blog ficará selecionado.");
      } else
        LogService::getInstance()->info("Nenhum blog estava selecionado anteriormente para {$platformName}.");

      DB::connection()->commit();
      return true;
    } catch (Exception $dbEx) {
      DB::connection()->rollBack();
      LogService::getInstance()->error("Erro ao substituir blogs para {$platformName} no banco local.", ['error' => $dbEx->getMessage()]);
      throw $dbEx;
    }
  }

}