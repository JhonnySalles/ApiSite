<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAcessosTable extends Migration {
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up() {
    Capsule::schema()->create('acessos', function (Blueprint $table) {
      $table->id();
      $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down() {
    Capsule::schema()->dropIfExists('acessos');
  }
}