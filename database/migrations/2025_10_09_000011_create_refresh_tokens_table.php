<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateRefreshTokensTable extends Migration {
  public function up() {
    Capsule::schema()->create('refresh_tokens', function (Blueprint $table) {
      $table->id();
      $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
      $table->string('token', 512)->unique();
      $table->timestamp('expires_at');
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('refresh_tokens');
  }
}