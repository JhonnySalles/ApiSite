<?php

$capsule = require __DIR__ . '/bootstrap/app.php';

use Illuminate\Database\Schema\Blueprint;

$schema = $capsule->getConnection()->getSchemaBuilder();
$seederTableName = 'seeders';

if (!$schema->hasTable($seederTableName)) {
  $schema->create($seederTableName, function (Blueprint $table) {
    $table->increments('id');
    $table->string('seeder');
    $table->integer('batch');
  });
  echo "Tabela 'seeders' de controle criada com sucesso.\n";
}

$executedSeeders = $capsule->table($seederTableName)->pluck('seeder')->all();

$seederPath = __DIR__ . '/database/seeders';
$allSeederFiles = scandir($seederPath);
$seederFiles = preg_grep('/\.php$/', $allSeederFiles);

if (empty($seederFiles)) {
  echo "Nenhum arquivo seeder encontrado.\n";
  exit;
}

$pendingSeeders = array_diff(
  array_map(fn($file) => pathinfo($file, PATHINFO_FILENAME), $seederFiles),
  $executedSeeders
);

if (empty($pendingSeeders)) {
  echo "Nenhum seeder novo para executar.\n";
  exit;
}

$lastBatch = $capsule->table($seederTableName)->max('batch') ?? 0;
$currentBatch = $lastBatch + 1;

echo "Executando seeders pendentes...\n";
sort($pendingSeeders);

foreach ($pendingSeeders as $seederName) {
  $filePath = $seederPath . '/' . $seederName . '.php';

  if (file_exists($filePath)) {
    require_once $filePath;

    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', substr($seederName, 18))));

    if (class_exists($className)) {
      (new $className())->run();
      $capsule->table($seederTableName)->insert(['seeder' => $seederName, 'batch' => $currentBatch]);
      echo "  - Seeded: $seederName\n";
    } else
      echo "  - AVISO: Classe '$className' não encontrada no arquivo '$seederName.php'. Verifique a nomeação.\n";
  }
}

echo "Seeders executados com sucesso!\n";