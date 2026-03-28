<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv = Dotenv::createMutable(__DIR__ . '/../', '.env.testing');
    $dotenv->load();
}
