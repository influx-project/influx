<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;

/**
 * Checks whether a service of one type is up.
 *
 * Implementations report failures through the result rather than throwing.
 */
interface Checker
{
    public function check(Service $service): CheckResult;
}
