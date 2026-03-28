<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ApiSite\Services\LogService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$appEnv = $_ENV['APP_ENV'] ?? 'local';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

if ($appEnv === 'testing') {
    if (file_exists(__DIR__ . '/../.env.testing')) {
        $dotenvTest = Dotenv::createMutable(__DIR__ . '/../', '.env.testing');
        $dotenvTest->load();
    }
}

$dbHost = $_ENV['DB_HOST'] ?? 'unknown';
$dbName = $_ENV['DB_DATABASE'] ?? 'unknown';

if ($appEnv === 'testing') {
    if (PHP_SAPI === 'cli') {
        echo "\n[DB] Testing Environment Detected. Connecting to: {$dbHost} / {$dbName}\n";
    }
}

$sentryEnv = $_ENV['SENTRY_ENVIRONMENT'] ?? 'development';
$sentryDsn = $_ENV['SENTRY_DSN'] ?? null;

if ($sentryDsn && $sentryDsn !== '') {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => $sentryEnv,
        'traces_sample_rate' => 1.0,
        'release' => 'apisite@1.0.0'
    ]);
}

global $globalCapsule;
if (!isset($globalCapsule)) {
    $globalCapsule = new Capsule;
    $globalCapsule->addConnection([
        'driver'    => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host'      => $_ENV['DB_HOST'] ?? 'localhost',
        'database'  => $_ENV['DB_DATABASE'] ?? '',
        'username'  => $_ENV['DB_USERNAME'] ?? 'root',
        'password'  => $_ENV['DB_PASSWORD'] ?? '',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ]);
    $globalCapsule->setAsGlobal();
    $globalCapsule->bootEloquent();
}

if ($sentryDsn && $sentryDsn !== '') {
    LogService::getInstance()->info("Sentry inicializado para o ambiente '{$sentryEnv}'.");
} elseif ($appEnv !== 'testing') {
    LogService::getInstance()->warning("Sentry DSN não configurado para o ambiente '{$sentryEnv}'. Erros não serão reportados.");
}

return $globalCapsule;