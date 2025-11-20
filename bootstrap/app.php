<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ApiSite\Services\LogService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$sentryEnv = $_ENV['SENTRY_ENVIRONMENT'] ?? 'development';
$sentryDsn = $_ENV['SENTRY_DSN'] ?? null;

if ($sentryDsn && $sentryDsn !== '') {
  Sentry\init([
    'dsn' => $sentryDsn,
    'environment' => $sentryEnv,
    'traces_sample_rate' => 1.0,
    'release' => 'apisite@1.0.0'
  ]);
}

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => $_ENV['DB_CONNECTION'],
    'host'      => $_ENV['DB_HOST'],
    'database'  => $_ENV['DB_DATABASE'],
    'username'  => $_ENV['DB_USERNAME'],
    'password'  => $_ENV['DB_PASSWORD'],
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();

$capsule->bootEloquent();

if ($sentryDsn && $sentryDsn !== '')
  LogService::getInstance()->info("Sentry inicializado para o ambiente '{$sentryEnv}'.");
else
  LogService::getInstance()->warning("Sentry DSN não configurado para o ambiente '{$sentryEnv}'. Erros não serão reportados.");

return $capsule;