<?php

namespace Tests\Extensions\Subscribers;

use PHPUnit\Event\Application\Finished;
use PHPUnit\Event\Application\FinishedSubscriber;
use Tests\Extensions\TestResultState;

class ApplicationFinished implements FinishedSubscriber {
    public function notify(Finished $event): void {
        $results = TestResultState::getSummary();
        
        echo "\n\e[1;47m\e[1;30m --- TEST SUMMARY --- \e[0m\n";
        
        foreach ($results as $result) {
            $color = ($result['status'] === 'PASSED') ? "\e[0;32m" : "\e[0;31m";
            echo $color . "[" . $result['status'] . "] " . $result['name'] . "\e[0m\n";
        }
        
        echo "\e[1;47m\e[1;30m -------------------- \e[0m\n\n";
    }
}
