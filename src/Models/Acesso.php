<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Acesso extends Model {
  protected $table = 'acessos';
  protected $fillable = ['user_id'];

  /**
   * Define a relação: um Acesso pertence a um User.
   */
  public function user() {
    return $this->belongsTo(User::class);
  }
}