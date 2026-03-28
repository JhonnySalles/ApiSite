<?php

namespace Tests\Extensions;

class TestResultState {
    private static $results = [];
    private static $currentTestClass = '';

    public static function setCurrentClass(string $className) {
        if (self::$currentTestClass !== $className) {
            self::$currentTestClass = $className;
            echo "\n\e[1;33mRunning: " . $className . "\e[0m\n";
        }
    }

    public static function addResult(string $testName, string $status) {
        $color = ($status === 'PASSED') ? "\e[0;32m" : "\e[0;31m";
        echo "  " . $color . "[" . $status . "] " . $testName . "\e[0m\n";
        
        self::$results[] = [
            'class' => self::$currentTestClass,
            'name' => $testName,
            'status' => $status
        ];
    }

    public static function getSummary() {
        return self::$results;
    }
}
