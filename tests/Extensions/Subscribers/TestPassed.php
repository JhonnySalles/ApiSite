<?php

namespace Tests\Extensions\Subscribers;

use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;
use Tests\Extensions\TestResultState;

class TestPassed implements PassedSubscriber {
    public function notify(Passed $event): void {
        $testName = $event->test()->nameWithClass();
        TestResultState::addResult($testName, 'PASSED');
    }
}
