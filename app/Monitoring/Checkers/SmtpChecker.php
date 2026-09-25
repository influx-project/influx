<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;

/**
 * Reads the SMTP greeting, which must be a 220 reply, then politely disconnects.
 */
class SmtpChecker extends SocketChecker
{
    protected function handshake($socket, Service $service): ?string
    {
        // Multi-line replies continue with "220-" and end with "220 ".
        do {
            $line = $this->readLine($socket);
        } while (strlen($line) > 3 && $line[3] === '-');

        $code = (int) substr($line, 0, 3);

        if ($code !== 220) {
            return 'Unexpected SMTP greeting: '.mb_substr($line, 0, 100);
        }

        @fwrite($socket, "QUIT\r\n");

        return null;
    }
}
