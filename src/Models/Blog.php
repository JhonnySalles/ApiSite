<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model {
  protected $table = 'blogs';
  protected $fillable = ['nome', 'titulo', 'selecionado', 'plataforma_id'];

  protected $casts = ['selecionado' => 'boolean',];
}