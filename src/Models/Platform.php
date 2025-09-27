<?php
namespace ApiSite\Models;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model {
  protected $table = 'plataformas';
  protected $fillable = ['nome', 'ativo'];
}