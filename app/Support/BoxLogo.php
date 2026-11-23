<?php

namespace App\Support;

use App\Models\Box;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoxLogo
{
    public static function response(Box $box): BinaryFileResponse
    {
        $path = $box->logo_path;
        abort_unless(is_string($path) && str_starts_with($path, "boxes/{$box->id}/") && ! str_contains($path, '..') && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
