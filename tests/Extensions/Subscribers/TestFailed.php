<?php

namespace Tests\Extensions\Subscribers;

use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use Tests\Extensions\TestResultState;

class TestFailed implements FailedSubscriber {
    public function notify(Failed $event): void {
        $testName = $event->test()->nameWithClass();
        TestResultState::addResult($testName, 'FAILED');
    }
}
