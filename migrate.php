<?php

$capsule = require __DIR__ . '/bootstrap/app.php';

use Illuminate\Database\Schema\Blueprint;

$schema = $capsule->getConnection()->getSchemaBuilder();
$migrationTableName = 'migrations';

if (!$schema->hasTable($migrationTableName)) {
  $schema->create($migrationTableName, function (Blueprint $table) {
    $table->increments('id');
    $table->string('migration');
    $table->integer('batch');
  });
  echo "Tabela 'migrations' criada com sucesso.\n";
}

$executedMigrations = $capsule->table($migrationTableName)->pluck('migration')->all();

$migrationPath = __DIR__ . '/database/migrations';
$allMigrationFiles = scandir($migrationPath);

$migrationFiles = preg_grep('/\.php$/', $allMigrationFiles);

if (empty($migrationFiles)) {
  echo "Nenhuma migration encontrada.\n";
  exit;
}

$pendingMigrations = array_diff(array_map(fn($file) => pathinfo($file, PATHINFO_FILENAME), $migrationFiles), $executedMigrations);

if (empty($pendingMigrations)) {
  echo "Nenhuma migration nova para executar.\n";
  exit;
}

$lastBatch = $capsule->table($migrationTableName)->max('batch') ?? 0;
$currentBatch = $lastBatch + 1;

echo "Executando migrations pendentes...\n";

sort($pendingMigrations);

foreach ($pendingMigrations as $migrationName) {
  $filePath = $migrationPath . '/' . $migrationName . '.php';
  if (file_exists($filePath)) {
    require_once $filePath;

    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', substr($migrationName, 18))));

    if (class_exists($className)) {
      (new $className())->up();

      $capsule->table($migrationTableName)->insert(['migration' => $migrationName, 'batch' => $currentBatch]);
      echo "  - Migrated: $migrationName\n";
    }
  }
}

echo "Migrations executadas com sucesso!\n";