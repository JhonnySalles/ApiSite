<?php

namespace ApiSite\Services;

use ApiSite\Models\Platform;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

class ConfigurationService {
  /**
   * Retorna todas as plataformas do banco de dados.
   */
  public function getPlatforms() {
    return Platform::orderBy('id')->get();
  }

  /**
   * Retorna uma única plataforma pelo nome.
   */
  public function getPlatformByName(string $nome) {
    $plataforma = Platform::where('nome', $nome)->first();
    if (!$plataforma)
      throw new InvalidArgumentException("Plataforma '$nome' não encontrada.");

    return $plataforma;
  }

  /**
   * Atualiza o status (ativo/inativo) de múltiplas plataformas em uma transação.
   * @param array $payload Array de plataformas para atualizar. Ex: [['id' => 1, 'ativa' => false]]
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
   * Atualiza o status (ativo/inativo) de uma única plataforma.
   * @param string $name O nome da plataforma a ser atualizada.
   * @param array $payload O corpo da requisição, ex: ['ativa' => true]
   */
  public function savePlatform(string $name, array $payload): Platform {
    $platform = $this->getPlatformByName($name);

    if (!isset($payload['ativa']))
      throw new InvalidArgumentException("O campo 'ativa' é obrigatório.");

    $platform->ativa = (bool)$payload['ativa'];
    $platform->save();

    return $platform;
  }
}