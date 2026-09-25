<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;
use Illuminate\Support\Facades\Process;

/**
 * Sends one ICMP echo request with the system `ping` binary, since raw sockets need root.
 */
class PingChecker implements Checker
{
    public function check(Service $service): CheckResult
    {
        $result = Process::timeout($service->timeout + 5)
            ->run(['ping', '-n', '-c', '1', '-W', (string) $service->timeout, $service->host]);

        if (! $result->successful()) {
            $output = trim($result->errorOutput()) ?: 'No reply';

            return CheckResult::down(mb_substr($output, 0, 200));
        }

        if (preg_match('/time[=<]\s*([\d.]+)\s*ms/', $result->output(), $matches) !== 1) {
            return CheckResult::down('Could not read the round-trip time from ping');
        }

        return CheckResult::up((float) $matches[1]);
    }
}
