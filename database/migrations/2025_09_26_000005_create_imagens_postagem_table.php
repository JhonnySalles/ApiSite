<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateImagensPostagemTable extends Migration {
  public function up() {
    Capsule::schema()->create('imagens_postagem', function (Blueprint $table) {
      $table->id();
      $table->foreignId('postagem_id')->constrained('postagens')->onDelete('cascade');
      $table->longText('url');
      $table->json('plataformas')->nullable();
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('imagens_postagem');
  }
}