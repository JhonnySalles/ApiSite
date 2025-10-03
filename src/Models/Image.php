<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model {
  protected $table = 'imagens_postagem';
  protected $fillable = ['postagem_id', 'url', 'plataformas'];
  protected $casts = ['plataformas' => 'array'];
}