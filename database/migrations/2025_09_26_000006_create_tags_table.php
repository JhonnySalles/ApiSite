<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class CreateTagsTable extends Migration {
  public function up() {
    Capsule::schema()->create('tags', function (Blueprint $table) {
      $table->id();
      $table->string('tag')->unique();
      $table->timestamps();
    });
  }

  public function down() {
    Capsule::schema()->dropIfExists('tags');
  }
}