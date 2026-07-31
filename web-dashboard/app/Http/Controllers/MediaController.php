<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Support\Facades\Http;

class MediaController extends Controller
{
    /**
     * Stream an uploaded photo from the middleware through the dashboard, so the
     * browser loads it same-origin. This avoids mixed-content blocking when the
     * dashboard is served over HTTPS but the middleware is plain HTTP, and means
     * only the dashboard server (not every browser) needs to reach the middleware.
     */
    public function upload(string $path, MiddlewareClient $client)
    {
        $path = ltrim($path, '/');
        // Only ever proxy the uploads directory — never arbitrary URLs.
        if (! str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            abort(404);
        }

        try {
            $resp = Http::timeout(20)->get($client->baseUrl().'/'.$path);
            if (! $resp->successful()) {
                abort(404);
            }
            return response($resp->body(), 200)
                ->header('Content-Type', $resp->header('Content-Type') ?: 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Throwable $e) {
            abort(404);
        }
    }
}
