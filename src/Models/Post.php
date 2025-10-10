<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model {
  protected $table = 'postagens';
  protected $fillable = ['texto', 'tipo', 'situacao', 'opcoes_plataforma', 'callback_url', 'data_postagem'];

  protected $casts = ['opcoes_plataforma' => 'array',];

  /**
   * Uma Postagem tem muitos Envios.
   */
  public function sends() {
    return $this->hasMany(Send::class, 'postagem_id');
  }

  /**
   * Uma Postagem tem muitas Imagens.
   */
  public function images() {
    return $this->hasMany(Image::class, 'postagem_id');
  }

  /**
   * Uma Postagem pode ter muitas Tags.
   */
  public function tags() {
    return $this->belongsToMany(Tag::class, 'postagem_tag', 'postagem_id', 'tag_id');
  }
}