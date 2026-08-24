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
        // Look up by query string, not a path segment: asset tags can contain
        // slashes/spaces (e.g. "PE/MYY-HQ/CHW/0009 (A)") which break URL paths.
        $json = $this->getJson('/api/assets', ['asset_tag' => $assetTag]);
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

    /**
     * Edit an existing audit result (e.g. correct its location). Only the keys
     * present in $payload are changed. Returns ['ok'=>bool,'status'=>int,'body'=>array].
     */
    public function updateResult(int $resultId, array $payload): array
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()
                ->put($this->baseUrl.'/api/audit/result/'.$resultId, $payload);
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

        // Return middleware-relative paths (e.g. "uploads/2026.../f.jpg"); the
        // views turn these into the same-origin /media proxy URL.
        return array_values(array_filter(array_map(
            fn ($img) => $img['path'] ?? null,
            $json['images']
        )));
    }

    /**
     * Like {@see resultImages()} but keeps each photo's server-side path (needed
     * to delete it) alongside its browser URL.
     *
     * @return array<int, array{path:string, url:string}>
     */
    public function resultImageList(int $auditResultId): array
    {
        $json = $this->getJson("/api/audit/result/{$auditResultId}/images");
        if ($json === null || ! is_array($json['images'] ?? null)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($img) {
            $p = (string) ($img['path'] ?? '');
            return $p !== '' ? ['path' => $p] : null;
        }, $json['images'])));
    }

    /** @return array<int, array<string, mixed>> Audit-trail entries (newest first). */
    public function auditTrail(array $filters = []): array
    {
        return $this->getData('/api/audit-trail', array_merge(['limit' => 300], $filters), 'data');
    }

    /** Delete a whole scanned audit result (archived server-side). */
    public function deleteResult(int $auditResultId, ?string $actor = null): array
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()
                ->delete($this->baseUrl.'/api/audit/result/'.$auditResultId, array_filter(['actor' => $actor]));
            return ['ok' => $resp->successful(), 'status' => $resp->status(), 'body' => $resp->json() ?? []];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'body' => ['message' => $e->getMessage()]];
        }
    }

    /** Delete one photo (file + DB rows) from an audit result. */
    public function deleteImage(int $auditResultId, string $path, ?string $actor = null): bool
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()
                ->delete($this->baseUrl.'/api/audit/result/'.$auditResultId.'/image', array_filter(['path' => $path, 'actor' => $actor]));
            if (! $resp->successful()) {
                $this->lastError = $resp->json('message') ?? "Delete failed (HTTP {$resp->status()}).";
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // ---- RBAC ----

    /** Effective permissions for a username: ['role_name','is_admin','permissions'=>[...]]. */
    public function resolvePermissions(string $username): ?array
    {
        return $this->getJson('/api/rbac/resolve/'.rawurlencode($username));
    }

    /** @return array<int, array<string, mixed>> */
    public function rbacRoles(): array
    {
        return $this->getData('/api/rbac/roles', [], 'data');
    }

    /** @return array<int, array<string, mixed>> */
    public function rbacUserRoles(): array
    {
        return $this->getData('/api/rbac/user-roles', [], 'data');
    }

    public function saveRbacRole(array $payload): bool
    {
        return $this->send('post', '/api/rbac/roles', $payload);
    }

    public function updateRbacRole(int $id, array $payload): bool
    {
        return $this->send('put', '/api/rbac/roles/'.$id, $payload);
    }

    public function deleteRbacRole(int $id): bool
    {
        return $this->send('delete', '/api/rbac/roles/'.$id);
    }

    public function assignRbacUser(string $username, int $roleId): bool
    {
        return $this->send('put', '/api/rbac/user-roles', ['username' => $username, 'role_id' => $roleId]);
    }

    public function removeRbacUser(string $username): bool
    {
        return $this->send('delete', '/api/rbac/user-roles/'.rawurlencode($username));
    }

    /** Small JSON request helper (post/put/delete) that records lastError. */
    protected function send(string $method, string $path, array $payload = []): bool
    {
        try {
            $resp = Http::timeout($this->timeout)->acceptJson()->{$method}($this->baseUrl.$path, $payload);
            if (! $resp->successful()) {
                $this->lastError = $resp->json('message') ?? "Middleware returned HTTP {$resp->status()} for {$path}.";
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Could not reach the middleware at {$this->baseUrl} ({$e->getMessage()}).";
            return false;
        }
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
