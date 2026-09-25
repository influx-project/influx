<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;

/**
 * Reads the identification banner SSH servers send before authentication.
 */
class SshChecker extends SocketChecker
{
    protected function handshake($socket, Service $service): ?string
    {
        $banner = $this->readLine($socket);

        return str_starts_with($banner, 'SSH-')
            ? null
            : 'Unexpected SSH banner: '.mb_substr($banner, 0, 100);
    }
}
