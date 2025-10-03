<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model {
  protected $table = 'postagens';
  protected $fillable = ['texto', 'tags', 'tipo', 'situacao', 'opcoes_plataforma', 'callback_url', 'data_postagem'];

  protected $casts = ['tags' => 'array', 'opcoes_plataforma' => 'array',];

  /**
   * Uma Postagem tem muitos Envios.
   */
  public function sends() {
    return $this->hasMany(Send::class);
  }

  /**
   * Uma Postagem tem muitas Imagens.
   */
  public function images() {
    return $this->hasMany(Image::class);
  }
}