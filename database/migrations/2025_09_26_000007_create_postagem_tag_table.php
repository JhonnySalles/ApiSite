<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePostagemTagTable extends Migration {
  public function up() {
    Capsule::schema()->create('postagem_tag', function (Blueprint $table) {
      $table->foreignId('postagem_id')->constrained('postagens')->onDelete('cascade');
      $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
      $table->primary(['postagem_id', 'tag_id']);
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('postagem_tag');
  }
}