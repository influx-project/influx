<?php

namespace App\Monitoring\Daemon;

use RuntimeException;

/**
 * Influx Daemon answered a request with an error, or with something that is not its API.
 */
class DaemonException extends RuntimeException
{
    public function __construct(
        string $message,
        /** The daemon's error code, such as `invalid_token`, or null if it did not send one. */
        public readonly ?string $errorCode = null,
        /** The HTTP status code of the response. */
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Determine whether the daemon has not taken its first sample yet, as after it restarts.
     */
    public function isNotReady(): bool
    {
        return $this->errorCode === 'not_ready';
    }
}
