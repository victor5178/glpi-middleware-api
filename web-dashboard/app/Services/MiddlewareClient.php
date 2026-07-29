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

    /** Look up a single asset by its asset_tag (returns null if not found). */
    public function getAsset(string $assetTag): ?array
    {
        $json = $this->getJson('/api/assets/'.rawurlencode($assetTag));
        return is_array($json) ? $json : null;
    }

    /**
     * Create or update an asset in the middleware inventory (non-destructive
     * upsert). Used when submitting an audit for an asset found in GLPI that
     * may not yet exist locally — mirrors the Android scan → submit flow.
     * Returns the asset's internal id, or null on failure.
     */
    public function upsertAsset(array $payload): ?int
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()
                ->post($this->baseUrl.'/api/assets', $payload);
            if (! $resp->successful()) {
                $body = $resp->json();
                $this->lastError = is_array($body) && isset($body['message'])
                    ? $body['message']
                    : "Middleware returned HTTP {$resp->status()} for /api/assets.";
                return null;
            }
            $id = $resp->json('id');
            return $id !== null ? (int) $id : null;
        } catch (\Throwable $e) {
            $this->lastError = "Could not reach the middleware at {$this->baseUrl} ({$e->getMessage()}).";
            return null;
        }
    }

    /**
     * Submit an audit result. Returns ['ok'=>bool, 'status'=>int, 'body'=>array].
     */
    public function submitAudit(array $payload): array
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()
                ->post($this->baseUrl.'/api/audit/submit', $payload);
            return ['ok' => $resp->successful(), 'status' => $resp->status(), 'body' => $resp->json() ?? []];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'body' => ['message' => $e->getMessage()]];
        }
    }

    /** Upload one photo file and link it to an audit_result. */
    public function uploadImage(int $auditResultId, string $filePath, string $originalName, ?string $computerName = null): bool
    {
        try {
            $fields = ['audit_result_id' => (string) $auditResultId];
            if (! empty($computerName)) {
                $fields['computer_name'] = $computerName;
            }
            $resp = Http::timeout($this->timeout)
                ->attach('image', file_get_contents($filePath), $originalName)
                ->post($this->baseUrl.'/api/audit/upload-image', $fields);
            return $resp->successful();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function scannedItems(int $auditId): array
    {
        return $this->getData("/api/audit/{$auditId}/scanned-items", ['limit' => 500], 'data');
    }

    /**
     * Full URLs of every photo attached to an audit result (may be more than
     * one). Falls back to an empty array if none / middleware unreachable.
     *
     * @return array<int, string>
     */
    public function resultImages(int $auditResultId): array
    {
        $json = $this->getJson("/api/audit/result/{$auditResultId}/images");
        if ($json === null || ! is_array($json['images'] ?? null)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($img) => isset($img['url']) ? $this->baseUrl.$img['url'] : null,
            $json['images']
        )));
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
