<?php

namespace App\Support;

use App\Models\Box;

class BoxTheme
{
    public static function color(?string $color, string $fallback): string
    {
        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $color ?? '') ? $color : $fallback;
    }

    public static function text(string $color): string
    {
        $rgb = array_map(function (string $value): float {
            $component = hexdec($value) / 255;

            return $component <= 0.04045 ? $component / 12.92 : (($component + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($color, 1), 2));
        $luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];

        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? '#000000' : '#FFFFFF';
    }

    public static function variables(?Box $box): string
    {
        $primary = self::color($box?->primary_color, '#C8FA3C');
        $secondary = self::color($box?->secondary_color, '#38BDF8');

        return "--box-primary:$primary;--box-secondary:$secondary;--box-primary-text:".self::text($primary).';--box-primary-foreground:'.self::text($primary).';--box-secondary-foreground:'.self::text($secondary).';--box-accent:'.self::accent($primary).';--box-secondary-accent:'.self::accent($secondary).';';
    }

    public static function accent(string $color): string
    {
        $channels = array_map('hexdec', str_split(substr($color, 1), 2));
        for ($step = 0; $step <= 20; $step++) {
            $candidate = '#'.implode('', array_map(fn ($value) => sprintf('%02x', (int) round($value + (255 - $value) * $step / 20)), $channels));
            $linear = array_map(function (string $value): float {
                $channel = hexdec($value) / 255;

                return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }, str_split(substr($candidate, 1), 2));
            $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
            if (($luminance + 0.05) / (0.02 + 0.05) >= 4.5) {
                return $candidate;
            }
        }

        return '#FFFFFF';
    }
}
