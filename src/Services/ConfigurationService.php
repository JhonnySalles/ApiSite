<?php

namespace ApiSite\Services;

use ApiSite\Models\Platform;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;

class ConfigurationService {
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
    $platform = Platform::where('nome', $name)->first();
    if (!$platform)
      throw new InvalidArgumentException("Plataforma '$name' não encontrada.");

    return $platform;
  }

  /**
   * Atualiza o status (ativo/inativo) de múltiplas plataformas em uma única transação.
   *
   * @param array $payload Um array de plataformas, onde cada item é um array associativo com 'id' e 'ativa'. Ex: [['id' => 1, 'ativa' => false]]
   * @return bool Retorna true se a operação for bem-sucedida.
   * @throws \Exception Se ocorrer um erro durante a transação no banco de dados.
   */
  public function savePlatforms(array $payload): bool {
    DB::connection()->beginTransaction();
    try {
      foreach ($payload as $p) {
        if (isset($p['id']) && isset($p['ativa']))
          Platform::where('id', $p['id'])->update(['ativa' => (bool)$p['ativa']]);
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
   * @param array $payload Um array associativo contendo a chave 'ativa'. Ex: ['ativa' => true]
   * @return Platform O objeto da plataforma com os dados atualizados.
   * @throws InvalidArgumentException Se a plataforma não for encontrada ou se a chave 'ativa' estiver ausente no payload.
   */
  public function savePlatform(string $name, array $payload): Platform {
    $platform = $this->getPlatformByName($name);

    if (!isset($payload['ativa']))
      throw new InvalidArgumentException("O campo 'ativa' é obrigatório.");

    $platform->ativa = (bool)$payload['ativa'];
    $platform->save();

    return $platform;
  }

  /**
   * Busca e retorna todos os blogs associados a uma plataforma específica.
   *
   * @param string $platform O nome da plataforma da qual os blogs serão buscados.
   * @return Collection A coleção de objetos Blog associados à plataforma.
   * @throws InvalidArgumentException Se a plataforma especificada não for encontrada.
   */
  public function getBlogsForPlatform(string $platform) {
    $data = $this->getPlatformByName($platform);
    return $data->blogs;
  }
}