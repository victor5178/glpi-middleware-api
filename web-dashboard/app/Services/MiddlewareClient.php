<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin client for the GLPI Node/Express middleware API.
 *
 * Read-only: the dashboard only consumes audit data and photos. Network or
 * decoding failures are swallowed and surfaced as empty results plus an
 * {@see self::$lastError} message, so a down middleware never 500s the page.
 */
class MiddlewareClient
{
    protected string $baseUrl;
    protected int $timeout;

    /** Human-readable message describing the most recent failure, if any. */
    public ?string $lastError = null;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.middleware.base_url'), '/');
        $this->timeout = (int) config('services.middleware.timeout', 15);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Public URL of the photo for an audit result (loaded by the browser). */
    public function imageUrl(int $auditResultId): string
    {
        return "{$this->baseUrl}/api/audit/result/{$auditResultId}/image";
    }

    /** @return array<int, array<string, mixed>> */
    public function audits(): array
    {
        return $this->getData('/api/audits', ['limit' => 200], 'data');
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(): array
    {
        return $this->getJson('/api/locations') ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function scannedItems(int $auditId): array
    {
        return $this->getData("/api/audit/{$auditId}/scanned-items", ['limit' => 500], 'data');
    }

    /**
     * GET a JSON endpoint and pull a nested key (e.g. paginated "data").
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getData(string $path, array $query, string $key): array
    {
        $json = $this->getJson($path, $query);
        if ($json === null) {
            return [];
        }

        return is_array($json[$key] ?? null) ? $json[$key] : [];
    }

    /** @return array<string, mixed>|array<int, mixed>|null */
    protected function getJson(string $path, array $query = []): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($this->baseUrl.$path, $query);

            if (! $response->successful()) {
                $this->lastError = "Middleware returned HTTP {$response->status()} for {$path}.";

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            $this->lastError = "Could not reach the middleware at {$this->baseUrl} ({$e->getMessage()}).";

            return null;
        }
    }
}
