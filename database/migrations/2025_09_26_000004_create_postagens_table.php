<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePostagensTable extends Migration {
  public function up() {
    Capsule::schema()->create('postagens', function (Blueprint $table) {
      $table->id();
      $table->text('texto')->nullable();
      $table->json('opcoes_plataforma')->nullable();
      $table->enum('tipo', ['RASCUNHO', 'POST'])->default('POST');
      $table->enum('situacao', ['PENDENTE', 'ENVIADO', 'SUCESSO', 'ALERTA', 'EXCLUIDO'])->default('PENDENTE');
      $table->string('callback_url')->nullable();
      $table->timestamp('data_postagem');
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('postagens');
  }
}