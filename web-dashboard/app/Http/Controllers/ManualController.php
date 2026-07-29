<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    /** Show the manual audit-entry form. */
    public function create(MiddlewareClient $client)
    {
        return view('manual', [
            'audits' => $client->audits(),
            'locations' => $client->locations(),
            'error' => $client->lastError,
        ]);
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
            'photos.*' => 'nullable|image|max:51200', // 50 MB, matches middleware
        ]);

        // Resolve the asset by its tag to get an internal id.
        $asset = $client->getAsset(trim($validated['asset_tag']));
        if ($asset === null || ! isset($asset['id'])) {
            return back()->withInput()->with('error', "Asset '{$validated['asset_tag']}' was not found in the inventory.");
        }

        $flag = fn (string $key) => $request->boolean($key) ? 1 : 0;

        $payload = [
            'audit_id' => (int) $validated['audit_id'],
            'asset_id' => (int) $asset['id'],
            'asset_found' => $flag('asset_found'),
            'actual_user' => $validated['actual_user'] ?? null,
            'actual_location_id' => (int) $validated['actual_location_id'],
            'is_physical_good' => $flag('is_physical_good'),
            'is_patch_latest' => $flag('is_patch_latest'),
            'is_endpoint_latest' => $flag('is_endpoint_latest'),
            'is_monitor_working' => $flag('is_monitor_working'),
            'is_ups_working' => $flag('is_ups_working'),
            'additional_info' => $validated['additional_info'] ?? null,
            'checked_by' => $validated['checked_by'],
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
}
