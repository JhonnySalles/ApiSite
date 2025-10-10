<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEnviosTable extends Migration {
  public function up() {
    Capsule::schema()->create('envios', function (Blueprint $table) {
      $table->id();
      $table->foreignId('postagem_id')->constrained('postagens')->onDelete('cascade');
      $table->foreignId('plataforma_id')->constrained('plataformas')->onDelete('cascade');
      $table->boolean('sucesso')->default(false);
      $table->text('erro')->nullable();
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('envios');
  }
}