<?php

namespace ApiSite\Services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

class LogService {
  private static ?Logger $instance = null;

  /**
   * Retorna uma instância única (Singleton) do Logger.
   */
  public static function getInstance(): Logger {
    if (self::$instance === null) {
      $log = new Logger('api');

      $formatter = new LineFormatter(
        "[%datetime%] %level_name%: %message% %context% %extra%\n",
        "Y-m-d H:i:s",
        true,
        true
      );

      $rotatingHandler = new RotatingFileHandler(__DIR__ . '/../../logs/api.log', 14, Level::Warning);
      $rotatingHandler->setFormatter($formatter);
      $log->pushHandler($rotatingHandler);

      $consoleHandler = new StreamHandler('php://stderr', Level::Debug);
      $consoleHandler->setFormatter($formatter);
      $log->pushHandler($consoleHandler);

      self::$instance = $log;
    }

    return self::$instance;
  }
}