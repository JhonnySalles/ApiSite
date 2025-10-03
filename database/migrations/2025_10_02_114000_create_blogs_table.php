<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBlogsTable extends Migration {
  public function up() {
    Capsule::schema()->create('blogs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('plataforma_id')->constrained('plataformas')->onDelete('cascade');
      $table->string('nome');
      $table->string('titulo');
      $table->boolean('selecionado')->default(false);
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('blogs');
  }
}