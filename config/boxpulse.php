<?php

$pollInterval = max(500, min(5000, (int) env('BOXPULSE_POLL_INTERVAL_MS', 1000)));

return [
    'poll_interval_ms' => $pollInterval,
    'controller_lease_seconds' => max(6, (int) ceil($pollInterval / 1000) * 4),
];
