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
    protected array $types = ['Computer', 'Monitor', 'Peripheral', 'Printer'];

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
    public function search(string $q, ?string $sessionToken = null): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        // Prefer the caller's GLPI session (the logged-in user). Only fall back
        // to a configured service account if no session token is available.
        $ownSession = false;
        $session = $sessionToken;
        if ($session === null || $session === '') {
            if (! $this->isConfigured()) {
                $this->lastError = 'GLPI is not configured. Log in again, or set GLPI_LOGIN / GLPI_PASSWORD for a service account.';
                return [];
            }
            $session = $this->initSession();
            if ($session === null) {
                return []; // lastError already set
            }
            $ownSession = true;
        } elseif ($this->baseUrl === '' || $this->appToken === '') {
            $this->lastError = 'GLPI is not configured on the server (GLPI_URL / GLPI_APP_TOKEN).';
            return [];
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
            // Only close a session we opened; never kill the login session.
            if ($ownSession) {
                $this->killSession($session);
            }
        }

        return $results;
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
