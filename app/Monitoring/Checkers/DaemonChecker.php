<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;
use App\Monitoring\Daemon\DaemonClient;
use App\Monitoring\Daemon\DaemonException;
use App\Monitoring\Stopwatch;
use Illuminate\Http\Client\ConnectionException;

/**
 * Asks the service's Influx Daemon for its latest sample. The service is up when the
 * daemon answers with one, or says it has not taken one yet, as it does just after starting.
 */
class DaemonChecker implements Checker
{
    public function check(Service $service): CheckResult
    {
        return $this->snapshot($service)['result'];
    }

    /**
     * Check the daemon and return its latest sample alongside the result, for live viewers.
     *
     * @return array{result: CheckResult, sample: array<string, mixed>|null}
     */
    public function snapshot(Service $service): array
    {
        $stopwatch = Stopwatch::start();

        try {
            $snapshot = DaemonClient::for($service)->snapshot();
        } catch (ConnectionException $e) {
            return ['result' => CheckResult::down($e->getMessage()), 'sample' => null];
        } catch (DaemonException $e) {
            $latency = $stopwatch->elapsedMs();

            return [
                'result' => $e->isNotReady()
                    ? CheckResult::up($latency)
                    : CheckResult::down($e->getMessage(), $latency, $e->status),
                'sample' => null,
            ];
        }

        return ['result' => CheckResult::up($stopwatch->elapsedMs()), 'sample' => $snapshot['sample']];
    }
}
