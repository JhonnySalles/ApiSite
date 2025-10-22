<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Send extends Model {
  protected $table = 'envios';
  protected $fillable = ['postagem_id', 'plataforma_id', 'sucesso', 'erro'];

  /**
   * Define a relação inversa: um Envio (Send) pertence a uma Plataforma (Platform).
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function platform() {
    return $this->belongsTo(Platform::class, 'plataforma_id');
  }

  /**
   * Define a relação inversa: um Envio (Send) pertence a um Post.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function post() {
    return $this->belongsTo(Post::class, 'postagem_id');
  }
}