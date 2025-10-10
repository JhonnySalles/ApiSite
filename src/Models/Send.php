<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Send extends Model {
  protected $table = 'envios';
  protected $fillable = ['postagem_id', 'plataforma_id', 'sucesso', 'erro'];

  /**
   * Um Envio pertence a uma Plataforma.
   */
  public function platform() {
    return $this->belongsTo(Platform::class,'postagem_id');
  }
}