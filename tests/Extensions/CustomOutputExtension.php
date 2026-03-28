<?php

namespace Tests\Extensions;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Tests\Extensions\Subscribers\TestPreparationStarted;
use Tests\Extensions\Subscribers\TestPassed;
use Tests\Extensions\Subscribers\TestFailed;
use Tests\Extensions\Subscribers\TestErrored;
use Tests\Extensions\Subscribers\ApplicationFinished;

class CustomOutputExtension implements Extension {
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {
        $facade->registerSubscribers(
            new TestPreparationStarted(),
            new TestPassed(),
            new TestFailed(),
            new TestErrored(),
            new ApplicationFinished()
        );
    }
}
