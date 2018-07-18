<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

class LoopReset extends BaseTestListener
{
    private $watcherCount;
    private $previousInfo;

    public function startTest(Test $test)
    {
        Loop::setErrorHandler(function (\Throwable $error) {
            \trigger_error((string) $error, \E_USER_ERROR);
        });

        $this->watcherCount = $this->countWatchers();
        $this->previousInfo = Loop::getInfo();
    }

    public function endTest(Test $test, $time)
    {
        (function () {
            $this->run(function () {
                // do nothing
            });
        })->bindTo($GLOBALS["__amp_scheduler"], \get_class($GLOBALS['__amp_scheduler']))();

        if (\count($GLOBALS["__amp_scheduler"]) !== 0) {
            \trigger_error("Test case left " . \count($GLOBALS["__amp_scheduler"]) . " pending async tasks", \E_USER_ERROR);
        }

        gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop

        if ($this->countWatchers() - $this->watcherCount !== 0) {
            $infoDiff = $this->calculateInfoDiff($this->previousInfo);
            \trigger_error("Event loop has remaining watchers after test: " . \json_encode($infoDiff, \JSON_PRETTY_PRINT), \E_USER_WARNING);
        }
    }

    private function countWatchers(): int
    {
        $info = Loop::getInfo();
        $total = 0;

        foreach (["defer", "delay", "repeat", "on_readable", "on_writable", "on_signal"] as $type) {
            foreach (["enabled", "disabled"] as $state) {
                $total += $info[$type][$state];
            }
        }

        return $total;
    }

    private function calculateInfoDiff(array $previous): array
    {
        $info = Loop::getInfo();

        foreach (["defer", "delay", "repeat", "on_readable", "on_writable", "on_signal"] as $type) {
            foreach (["enabled", "disabled"] as $state) {
                $info[$type][$state] -= $previous[$type][$state];
            }
        }

        return $info;
    }
}
