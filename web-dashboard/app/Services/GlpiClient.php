<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Searches the GLPI REST API (apirest.php) directly for assets by serial
 * number, assigned user (contact) or GLPI id. Server-side only — GLPI
 * credentials never reach the browser.
 */
class GlpiClient
{
    protected string $baseUrl;
    protected string $appToken;
    protected string $login;
    protected string $password;
    protected int $timeout;

    /** GLPI item types to search. */
    protected array $types = ['Computer', 'Monitor', 'Peripheral', 'Printer', 'NetworkEquipment'];

    public ?string $lastError = null;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.glpi.base_url'), '/');
        $this->appToken = (string) config('services.glpi.app_token');
        $this->login = (string) config('services.glpi.login');
        $this->password = (string) config('services.glpi.password');
        $this->timeout = (int) config('services.glpi.timeout', 15);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->appToken !== ''
            && $this->login !== '' && $this->password !== '';
    }

    /**
     * Verifies a username/password against GLPI (used by the dashboard login).
     * Only needs GLPI_URL + GLPI_APP_TOKEN. Returns the GLPI session token on
     * success (kept alive so it can be reused for searching), or null.
     */
    public function attemptLogin(string $username, string $password): ?string
    {
        if ($this->baseUrl === '' || $this->appToken === '') {
            $this->lastError = 'GLPI is not configured on the server (GLPI_URL / GLPI_APP_TOKEN).';
            return null;
        }
        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode("{$username}:{$password}"),
                    'App-Token' => $this->appToken,
                ])
                ->get($this->baseUrl.'/initSession');

            if (! $resp->successful()) {
                $body = $resp->json();
                $msg = is_array($body) ? implode(' — ', array_filter($body, 'is_string')) : $resp->body();
                $this->lastError = 'Login failed (HTTP '.$resp->status().')'.($msg ? ': '.$msg : '');
                return null;
            }
            $token = $resp->json('session_token');
            if (! $token) {
                $this->lastError = 'GLPI did not return a session token.';
                return null;
            }
            return $token;
        } catch (\Throwable $e) {
            $this->lastError = 'Could not reach GLPI at '.$this->baseUrl.' ('.$e->getMessage().').';
            return null;
        }
    }

    /** Public wrapper so the auth flow can close a GLPI session on logout. */
    public function endSession(string $sessionToken): void
    {
        $this->killSession($sessionToken);
    }

    /**
     * Search GLPI by a free-text query (serial / user / id).
     *
     * @return array<int, array<string, mixed>> normalized results
     */
    public function search(string $q, ?string $username = null, ?string $password = null): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        // Open a FRESH GLPI session for each search — either with the logged-in
        // user's credentials (auto re-auth, so a stale token can never happen)
        // or a configured service account. Always closed afterwards.
        if ($username !== null && $username !== '' && $password !== null && $password !== '') {
            $session = $this->attemptLogin($username, $password);
        } elseif ($this->isConfigured()) {
            $session = $this->initSession();
        } else {
            $this->lastError = 'GLPI is not configured. Log in again, or set GLPI_LOGIN / GLPI_PASSWORD for a service account.';
            return [];
        }
        if ($session === null) {
            return []; // lastError already set
        }

        $seen = [];
        $results = [];
        $add = function (string $type, $item) use (&$seen, &$results) {
            $id = $item['id'] ?? null;
            if ($id === null) {
                return;
            }
            $key = $type.':'.$id;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $results[] = [
                'type' => $type,
                'id' => $id,
                'name' => $item['name'] ?? null,
                'serial' => $item['serial'] ?? null,
                'otherserial' => $item['otherserial'] ?? null,
                'contact' => $item['contact'] ?? null,
            ];
        };

        try {
            $isNumeric = ctype_digit($q);
            foreach ($this->types as $type) {
                foreach ($this->searchType($type, 'serial', $q, $session) as $it) {
                    $add($type, $it);
                }
                foreach ($this->searchType($type, 'contact', $q, $session) as $it) {
                    $add($type, $it);
                }
                if ($isNumeric) {
                    $byId = $this->getById($type, (int) $q, $session);
                    if ($byId !== null) {
                        $add($type, $byId);
                    }
                }
            }
        } finally {
            $this->killSession($session);
        }

        return $results;
    }

    /**
     * List GLPI assets across the searched item types (optionally filtered by a
     * free-text term matched against name / serial / asset tag / user /
     * location). Used by Asset Review to reconcile inventory against audits.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param array<int, string> $types GLPI item types to include (empty = all)
     */
    public function listAssets(?string $username, ?string $password, string $filter = '', bool $activeOnly = false, array $types = []): array
    {
        $typesToScan = ! empty($types)
            ? array_values(array_intersect($this->types, $types))
            : $this->types;

        if ($username !== null && $username !== '' && $password !== null && $password !== '') {
            $session = $this->attemptLogin($username, $password);
        } elseif ($this->isConfigured()) {
            $session = $this->initSession();
        } else {
            $this->lastError = 'GLPI is not configured. Log in again, or set GLPI_LOGIN / GLPI_PASSWORD for a service account.';
            return [];
        }
        if ($session === null) {
            return [];
        }

        $needle = trim(mb_strtolower($filter));
        $results = [];
        try {
            foreach ($typesToScan as $type) {
                foreach ($this->listType($type, $session) as $it) {
                    // With expand_dropdowns, states_id is the status NAME (e.g. "Active").
                    $status = is_string($it['states_id'] ?? null) ? trim($it['states_id']) : null;

                    // Skip anything that isn't Active when requested.
                    if ($activeOnly && ! $this->isActiveStatus($status)) {
                        continue;
                    }

                    // Registered user: prefer the assigned user (users_id, a name
                    // via expand_dropdowns), fall back to the free-text contact.
                    $regUser = is_string($it['users_id'] ?? null) ? $it['users_id']
                        : (is_string($it['contact'] ?? null) ? $it['contact'] : null);

                    $entry = [
                        'type' => $type,
                        'id' => $it['id'] ?? null,
                        'name' => $this->cleanText($it['name'] ?? null),
                        'serial' => $this->cleanText($it['serial'] ?? null),
                        'otherserial' => $this->cleanText($it['otherserial'] ?? null),
                        'contact' => $this->cleanText($it['contact'] ?? null),
                        'user' => $this->cleanText($regUser),
                        'location' => $this->cleanLocation(is_string($it['locations_id'] ?? null) ? $it['locations_id'] : null),
                        'status' => $status,
                    ];
                    if ($needle !== '') {
                        $hay = mb_strtolower(implode(' ', array_filter([
                            $entry['name'], $entry['serial'], $entry['otherserial'], $entry['contact'], $entry['location'],
                        ], fn ($v) => is_string($v) && $v !== '')));
                        if (! str_contains($hay, $needle)) {
                            continue;
                        }
                    }
                    $results[] = $entry;
                }
            }
        } finally {
            $this->killSession($session);
        }

        return $results;
    }

    /** Decode HTML entities GLPI returns (e.g. "&#62;" → ">", "&amp;" → "&"). */
    protected function cleanText(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Clean a GLPI location: decode entities and turn the completename hierarchy
     * separator ("A > B") into the comma form used by scanned sites ("A , B").
     */
    protected function cleanLocation(?string $v): ?string
    {
        $v = $this->cleanText($v);
        if ($v === null || $v === '') {
            return $v;
        }
        return trim(preg_replace('/\s*>\s*/', ' , ', $v));
    }

    /**
     * Whether a GLPI status name counts as "active". Configurable via
     * GLPI_ACTIVE_STATUSES (comma-separated); defaults to common active labels.
     */
    protected function isActiveStatus(?string $status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }
        $active = array_filter(array_map('trim', explode(',', (string) config('services.glpi.active_statuses', 'Active,In use,In-use,Inuse'))));
        foreach ($active as $a) {
            if (strcasecmp($status, $a) === 0) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, array<string, mixed>> */
    protected function listType(string $type, string $session): array
    {
        // expand_dropdowns returns location/user as names (not ids) for filtering.
        $url = $this->baseUrl.'/'.$type.'?range=0-999&expand_dropdowns=true';
        try {
            $resp = Http::timeout($this->timeout * 2)
                ->withHeaders(['App-Token' => $this->appToken, 'Session-Token' => $session])
                ->get($url);
            $json = $resp->successful() ? $resp->json() : null;
            return is_array($json) ? array_filter($json, 'is_array') : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function initSession(): ?string
    {
        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode("{$this->login}:{$this->password}"),
                    'App-Token' => $this->appToken,
                ])
                ->get($this->baseUrl.'/initSession');

            if (! $resp->successful()) {
                $this->lastError = 'GLPI login failed (HTTP '.$resp->status().'): '.$resp->body();
                return null;
            }
            $token = $resp->json('session_token');
            if (! $token) {
                $this->lastError = 'GLPI did not return a session token.';
                return null;
            }
            return $token;
        } catch (\Throwable $e) {
            $this->lastError = 'Could not reach GLPI at '.$this->baseUrl.' ('.$e->getMessage().').';
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function searchType(string $type, string $field, string $value, string $session): array
    {
        // Build the URL manually so the searchText[field] brackets stay literal.
        $url = $this->baseUrl.'/'.$type.'?searchText['.$field.']='.rawurlencode($value).'&range=0-19';
        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders(['App-Token' => $this->appToken, 'Session-Token' => $session])
                ->get($url);
            $json = $resp->successful() ? $resp->json() : null;
            return is_array($json) ? array_filter($json, 'is_array') : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    protected function getById(string $type, int $id, string $session): ?array
    {
        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders(['App-Token' => $this->appToken, 'Session-Token' => $session])
                ->get($this->baseUrl.'/'.$type.'/'.$id);
            if (! $resp->successful()) {
                return null;
            }
            $json = $resp->json();
            return (is_array($json) && isset($json['id'])) ? $json : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function killSession(string $session): void
    {
        try {
            Http::timeout(5)
                ->withHeaders(['App-Token' => $this->appToken, 'Session-Token' => $session])
                ->get($this->baseUrl.'/killSession');
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
