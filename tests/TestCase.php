<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Mockery;

abstract class TestCase extends BaseTestCase {
    protected static $capsule;
    protected static $migrationsExecuted = false;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        if (!self::$capsule) {
            self::$capsule = require __DIR__ . '/../bootstrap/app.php';
        }

        self::runMigrations();
    }

    protected static function runMigrations() {
        if (self::$migrationsExecuted) return;

        $capsule = self::$capsule;
        $schema = $capsule->getConnection()->getSchemaBuilder();

        $tables = $capsule->getConnection()->select('SHOW TABLES');
        $dbName = $_ENV['DB_DATABASE'] ?? 'apisite_test';
        $key = "Tables_in_" . $dbName;

        $capsule->getConnection()->statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            if (isset($table->$key)) {
                $tableName = $table->$key;
                $schema->drop($tableName);
            }
        }
        $capsule->getConnection()->statement('SET FOREIGN_KEY_CHECKS = 1');

        require_once __DIR__ . '/../migrate.php';
        require_once __DIR__ . '/../seed.php';

        self::$migrationsExecuted = true;
    }

    protected function tearDown(): void {
        parent::tearDown();
        Mockery::close();
    }
}
