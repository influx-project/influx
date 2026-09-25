<?php

namespace App\Monitoring;

/**
 * Measures elapsed time with the monotonic clock.
 */
final class Stopwatch
{
    private function __construct(private readonly int|float $startedAt) {}

    public static function start(): self
    {
        return new self(hrtime(true));
    }

    public function elapsedMs(): float
    {
        return (hrtime(true) - $this->startedAt) / 1e6;
    }
}
