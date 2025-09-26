<?php

require __DIR__ . '/bootstrap/app.php';

$seederPath = __DIR__ . '/database/seeders';
$allSeederFiles = scandir($seederPath);

$seederFiles = preg_grep('/\.php$/', $allSeederFiles);

if (empty($seederFiles)) {
  echo "Nenhum seeder encontrado.\n";
  exit;
}

echo "Executando seeders...\n";

sort($seederFiles);

foreach ($seederFiles as $fileName) {
  $filePath = $seederPath . '/' . $fileName;

  if (file_exists($filePath)) {
    require_once $filePath;

    $className = pathinfo($fileName, PATHINFO_FILENAME);
    $className = substr($className, 18);
    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));

    if (class_exists($className)) {
      echo "  - Executando seeder: $className\n";
      (new $className())->run();
    } else
      echo "  - Aviso: A classe '$className' não foi encontrada no arquivo '$fileName'.\n";
  }
}

echo "Seeders executados com sucesso!\n";