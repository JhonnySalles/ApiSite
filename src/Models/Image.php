<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model {
  protected $table = 'postagem_imagens';
  protected $fillable = ['postagem_id', 'url', 'plataformas'];
  protected $casts = ['plataformas' => 'array'];
}