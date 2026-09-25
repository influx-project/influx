<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;

/**
 * Only opens a connection to the port. Also used for databases, since most
 * engines expect credentials before they say anything.
 */
class TcpChecker extends SocketChecker
{
    protected function handshake($socket, Service $service): ?string
    {
        return null;
    }
}
