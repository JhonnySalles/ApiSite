<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class Access extends Model {
  protected $table = 'acessos';
  protected $fillable = ['usuario_id'];

  /**
   * Define a relação: um Access pertence a um User.
   */
  public function user() {
    return $this->belongsTo(User::class);
  }
}