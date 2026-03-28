<?php

namespace Tests\Extensions\Subscribers;

use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use Tests\Extensions\TestResultState;

class TestErrored implements ErroredSubscriber {
    public function notify(Errored $event): void {
        $testName = $event->test()->nameWithClass();
        TestResultState::addResult($testName, 'ERROR');
    }
}
