<?php

namespace ApiSite\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {
  /**
   * O nome da tabela associada ao modelo.
   * @var string
   */
  protected $table = 'usuarios';

  /**
   * Os atributos que podem ser atribuídos em massa.
   *
   * @var array
   */
  protected $fillable = ['nome', 'username', 'password',];

  /**
   * Os atributos que devem ser ocultados para arrays.
   *
   * @var array
   */
  protected $hidden = ['password', 'remember_token',];
}