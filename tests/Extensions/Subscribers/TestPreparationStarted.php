<?php

namespace Tests\Extensions\Subscribers;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Tests\Extensions\TestResultState;

class TestPreparationStarted implements PreparationStartedSubscriber {
    public function notify(PreparationStarted $event): void {
        $className = $event->test()->className();
        TestResultState::setCurrentClass($className);
    }
}
