<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model {
  protected $table = 'refresh_tokens';

  protected $fillable = ['usuario_id', 'token', 'expires_at',];

  public function user() {
    return $this->belongsTo(User::class,'usuario_id');
  }
}