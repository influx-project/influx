<?php

namespace App\Monitoring;

/**
 * The outcome of checking a service once.
 */
final readonly class CheckResult
{
    public function __construct(
        public bool $successful,
        public ?float $latencyMs = null,
        public ?int $statusCode = null,
        public ?string $error = null,
    ) {}

    /**
     * The service responded as expected.
     */
    public static function up(float $latencyMs, ?int $statusCode = null): self
    {
        return new self(true, round($latencyMs, 2), $statusCode);
    }

    /**
     * The service did not respond, or responded with an error.
     */
    public static function down(string $error, ?float $latencyMs = null, ?int $statusCode = null): self
    {
        return new self(false, $latencyMs === null ? null : round($latencyMs, 2), $statusCode, mb_substr($error, 0, 500));
    }
}
