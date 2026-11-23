<?php

$pollInterval = max(500, min(5000, (int) env('BOXPULSE_POLL_INTERVAL_MS', 1000)));

return [
    'poll_interval_ms' => $pollInterval,
    'controller_lease_seconds' => max(6, (int) ceil($pollInterval / 1000) * 4),
    'tv_end_seconds' => max(0, min(60, (int) env('BOXPULSE_TV_END_SECONDS', 8))),
];
