<?php

namespace App\Support;

use App\Models\Box;
use Illuminate\Support\Facades\Cache;

class BoxTvLink
{
    private const HEARTBEAT_INTERVAL = 10;

    public static function url(Box $box): ?string
    {
        return $box->tv_access_token ? route('box.tv.open').'#'.$box->tv_access_token : null;
    }

    public static function heartbeat(Box $box): void
    {
        $now = now()->timestamp;
        if ((self::lastSeen($box) ?? 0) <= $now - self::HEARTBEAT_INTERVAL) {
            Cache::put(self::heartbeatKey($box), $now, now()->addDay());
        }
    }

    /** @return array{online: bool, last_seen_at: ?string} */
    public static function presence(Box $box): array
    {
        $lastSeen = self::lastSeen($box);

        return [
            'online' => $lastSeen !== null && $lastSeen + 25 > now()->timestamp,
            'last_seen_at' => $lastSeen === null ? null : now($box->timezone)->setTimestamp($lastSeen)->format('d/m H:i:s'),
        ];
    }

    private static function lastSeen(Box $box): ?int
    {
        return $box->tv_access_hash ? Cache::get(self::heartbeatKey($box)) : null;
    }

    private static function heartbeatKey(Box $box): string
    {
        return 'box-tv:seen:'.$box->tv_access_hash;
    }
}
