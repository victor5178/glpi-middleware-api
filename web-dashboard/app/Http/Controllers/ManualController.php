<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use App\Services\MiddlewareClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ManualController extends Controller
{
    /** Show the manual audit-entry form (with an optional GLPI asset search). */
    public function create(Request $request, MiddlewareClient $client, GlpiClient $glpi)
    {
        // GLPI asset search (by serial / user / id), reusing the login session.
        $q = trim((string) $request->query('q', ''));
        $glpiResults = [];
        $glpiError = null;
        if ($q !== '') {
            // Re-authenticate to GLPI with the stored (encrypted) credentials.
            $username = $request->session()->get('glpi_user');
            $password = null;
            if ($enc = $request->session()->get('glpi_pw')) {
                try {
                    $password = Crypt::decryptString($enc);
                } catch (\Throwable $e) {
                    $password = null;
                }
            }

            // Sessions created before the auto-reauth update have a username but
            // no stored password — searching would fail with a confusing config
            // error. Clear the stale session and send the user to a fresh login
            // (which stores the encrypted password searches re-authenticate with).
            if (($password === null || $password === '') && ! $glpi->isConfigured()) {
                $request->session()->forget(['glpi_user', 'glpi_pw']);
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->with('error', 'Please sign in again to search GLPI (your session predates a security update).');
            }

            $glpiResults = $glpi->search($q, $username, $password);
            $glpiError = $glpi->lastError;
        }

        return view('manual', [
            'audits' => $client->audits(),
            'locations' => $client->locations(),
            'error' => $client->lastError,
            'q' => $q,
            'glpiResults' => $glpiResults,
            'glpiError' => $glpiError,
        ]);
    }

    /** Camera-based QR scan page (mobile browser). Decodes → manual entry. */
    public function scan()
    {
        return view('scan');
    }

    /** Submit a manually entered audit result + uploaded photos. */
    public function store(Request $request, MiddlewareClient $client)
    {
        $validated = $request->validate([
            'audit_id' => 'required|integer',
            'asset_tag' => 'required|string|max:100',
            'actual_location_id' => 'required|integer',
            'checked_by' => 'required|string|max:50',
            'actual_user' => 'nullable|string|max:50',
            'additional_info' => 'nullable|string',
            'glpi_id' => 'nullable|integer',
            'serial_number' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'model' => 'nullable|string|max:100',
            'assigned_user' => 'nullable|string|max:100',
            'photos.*' => 'nullable|image|max:51200', // 50 MB, matches middleware
        ]);

        $assetTag = trim($validated['asset_tag']);

        // Resolve the asset by its tag to get an internal id.
        $asset = $client->getAsset($assetTag);
        $assetId = (is_array($asset) && isset($asset['id'])) ? (int) $asset['id'] : null;

        // If it isn't in the local inventory yet but the GLPI search carried its
        // details, create/update it first — same as the mobile scan → submit flow.
        if ($assetId === null && ! empty($validated['category'])) {
            $assetId = $client->upsertAsset([
                'glpi_id' => $validated['glpi_id'] ?? null,
                'asset_tag' => $assetTag,
                'serial_number' => $validated['serial_number'] ?? null,
                'model' => $validated['model'] ?? null,
                'category' => $this->normalizeCategory($validated['category']),
                'assigned_user' => $validated['assigned_user'] ?? null,
                // assets.location_id is NOT NULL — seed a new asset with the
                // location being audited (COALESCE keeps it on later updates).
                'location_id' => (int) $validated['actual_location_id'],
            ]);
        }

        if ($assetId === null) {
            $msg = $client->lastError
                ? "Could not save asset '{$assetTag}': {$client->lastError}"
                : "Asset '{$assetTag}' was not found in the inventory. Search GLPI above and use “Use this asset” to link it.";
            return back()->withInput()->with('error', $msg);
        }

        $flag = fn (string $key) => $request->boolean($key) ? 1 : 0;

        $payload = [
            'audit_id' => (int) $validated['audit_id'],
            'asset_id' => $assetId,
            'asset_found' => $flag('asset_found'),
            'actual_user' => $validated['actual_user'] ?? null,
            'actual_location_id' => (int) $validated['actual_location_id'],
            'is_physical_good' => $flag('is_physical_good'),
            'is_patch_latest' => $flag('is_patch_latest'),
            'is_endpoint_latest' => $flag('is_endpoint_latest'),
            'is_monitor_working' => $flag('is_monitor_working'),
            'is_ups_working' => $flag('is_ups_working'),
            'additional_info' => $validated['additional_info'] ?? null,
            // Authoritative: always the logged-in user, not whatever was posted.
            'checked_by' => $request->session()->get('glpi_user') ?: $validated['checked_by'],
        ];

        $result = $client->submitAudit($payload);
        if (! $result['ok']) {
            $msg = $result['body']['message'] ?? 'Failed to submit audit.';
            return back()->withInput()->with('error', $msg);
        }

        $auditResultId = (int) ($result['body']['id'] ?? 0);

        // Upload each selected photo file.
        $uploaded = 0;
        $failed = 0;
        foreach ((array) $request->file('photos', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $ok = $client->uploadImage(
                $auditResultId,
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $validated['asset_tag']
            );
            $ok ? $uploaded++ : $failed++;
        }

        $flash = "Audit saved (#$auditResultId).";
        if ($uploaded > 0) $flash .= " $uploaded photo(s) uploaded.";
        if ($failed > 0) $flash .= " $failed photo(s) failed.";

        return redirect()
            ->route('scanned.show', ['auditId' => (int) $validated['audit_id'], 'resultId' => $auditResultId])
            ->with('success', $flash);
    }

    /**
     * Map a GLPI item type to a category the middleware's assets table accepts.
     * The middleware allows: Desktop, Laptop, Monitor, Printer, UPS, Computer,
     * Peripheral. Anything unknown falls back to Computer.
     */
    protected function normalizeCategory(string $type): string
    {
        $allowed = ['Desktop', 'Laptop', 'Monitor', 'Printer', 'UPS', 'Computer', 'Peripheral'];
        foreach ($allowed as $c) {
            if (strcasecmp($type, $c) === 0) {
                return $c;
            }
        }
        return 'Computer';
    }
}
