<?php

namespace App\Repositories\Contracts;

use App\Repositories\Data\ProbeResult;

interface StoreProbe
{
    /**
     * One cheap call to the store. Never throws: any failure is reported
     * as reachable = false.
     */
    public function check(): ProbeResult;
}
