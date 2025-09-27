<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePlataformasTable extends Migration {
  public function up() {
    Capsule::schema()->create('plataformas', function (Blueprint $table) {
      $table->id();
      $table->string('nome')->unique();
      $table->boolean('ativo')->default(true);
      $table->json('opcional')->nullable();
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('plataformas');
  }
}