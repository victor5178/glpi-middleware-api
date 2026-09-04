<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, MiddlewareClient $client)
    {
        $audits = $client->audits();

        // Selected audit: query param, else the most recent audit.
        $selectedAuditId = (int) $request->query('audit_id', 0);
        if ($selectedAuditId <= 0 && ! empty($audits)) {
            $selectedAuditId = (int) ($audits[0]['id'] ?? 0);
        }

        $items = $selectedAuditId > 0 ? $client->scannedItems($selectedAuditId) : [];

        $stats = [
            'total' => count($items),
            'found' => $this->countWhere($items, 'asset_found', 1),
            'missing' => count($items) - $this->countWhere($items, 'asset_found', 1),
            'with_photo' => count(array_filter($items, fn ($i) => ! empty($i['img_dir']))),
        ];

        $selectedAudit = collect($audits)->firstWhere('id', $selectedAuditId);

        $groups = $this->groupBySiteAndUser($items, $client->locations(), $selectedAudit);

        return view('dashboard', [
            'audits' => $audits,
            'groups' => $groups,
            'stats' => $stats,
            'selectedAuditId' => $selectedAuditId,
            'selectedAudit' => $selectedAudit,
            'client' => $client,
            'error' => $client->lastError,
        ]);
    }

    public function show(int $auditId, int $resultId, MiddlewareClient $client)
    {
        $item = $this->findResult($client, $auditId, $resultId);
        abort_if($item === null, 404, 'Scanned record not found.');

        // All photos for this record (middleware-relative paths; there may be
        // more than one). Fall back to the single img_dir if the list is empty.
        $images = $client->resultImages($resultId);
        if (empty($images) && ! empty($item['img_dir'])) {
            $images = [$item['img_dir']];
        }

        return view('detail', [
            'item' => $item,
            'auditId' => $auditId,
            'images' => $images,
            'client' => $client,
            'locationLabel' => $this->locationLabel($client->locations(), $item['actual_location_id'] ?? null),
        ]);
    }

    /** Show the edit form for a scanned audit result. */
    public function edit(int $auditId, int $resultId, MiddlewareClient $client)
    {
        $item = $this->findResult($client, $auditId, $resultId);
        abort_if($item === null, 404, 'Scanned record not found.');

        return view('edit', [
            'item' => $item,
            'auditId' => $auditId,
            'resultId' => $resultId,
            'locations' => $client->locations(),
            'images' => $client->resultImageList($resultId),
        ]);
    }

    /** Persist edits to a scanned audit result (location, user, checklist, notes). */
    public function update(int $auditId, int $resultId, Request $request, MiddlewareClient $client)
    {
        $validated = $request->validate([
            'actual_location_id' => 'required|integer',
            'actual_user' => 'nullable|string|max:100',
            'additional_info' => 'nullable|string',
            'fa_tagging' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:100',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'string',
            'photos.*' => 'nullable|image|max:51200', // 50 MB, matches middleware
        ]);

        $flag = fn (string $key) => $request->boolean($key) ? 1 : 0;

        $actor = $request->session()->get('glpi_user');

        $payload = [
            'actor' => $actor,
            'actual_location_id' => (int) $validated['actual_location_id'],
            'actual_user' => $validated['actual_user'] ?? null,
            'additional_info' => $validated['additional_info'] ?? null,
            'asset_found' => $flag('asset_found'),
            'is_physical_good' => $flag('is_physical_good'),
            'is_patch_latest' => $flag('is_patch_latest'),
            'is_endpoint_latest' => $flag('is_endpoint_latest'),
            'is_monitor_working' => $flag('is_monitor_working'),
            'is_ups_working' => $flag('is_ups_working'),
            'led_normal' => $flag('led_normal'),
            'no_fault' => $flag('no_fault'),
            'fa_tagging' => $validated['fa_tagging'] ?? null,
        ];

        $result = $client->updateResult($resultId, $payload);
        if (! $result['ok']) {
            $status = (int) ($result['status'] ?? 0);
            $msg = $result['body']['message'] ?? ('Update failed (HTTP '.$status.').');
            if ($status === 404) {
                $msg = 'The update endpoint was not found (HTTP 404) — restart the middleware so the new '
                     .'PUT /api/audit/result/:id route loads, then try again.';
            } elseif ($status === 0) {
                $msg = 'Could not reach the middleware to save the change. '.$msg;
            }
            return back()->withInput()->with('error', $msg);
        }

        // Photo edits: delete the ones ticked for removal, then add any uploads.
        $removed = 0;
        foreach ((array) $request->input('remove_images', []) as $path) {
            if ($path !== '' && $client->deleteImage($resultId, $path, $actor)) {
                $removed++;
            }
        }

        $added = 0;
        foreach ((array) $request->file('photos', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            if ($client->uploadImage($resultId, $file->getRealPath(), $file->getClientOriginalName(), $validated['asset_tag'] ?? null)) {
                $added++;
            }
        }

        $flash = 'Record updated.';
        if ($removed > 0) $flash .= " $removed photo(s) removed.";
        if ($added > 0) $flash .= " $added photo(s) added.";

        return redirect()
            ->route('scanned.show', ['auditId' => $auditId, 'resultId' => $resultId])
            ->with('success', $flash);
    }

    /** Delete a scanned audit result (archived to the audit trail server-side). */
    public function destroy(int $auditId, int $resultId, Request $request, MiddlewareClient $client)
    {
        $result = $client->deleteResult($resultId, $request->session()->get('glpi_user'));
        if (! $result['ok']) {
            $msg = $result['body']['message'] ?? 'Failed to delete the record.';
            return back()->with('error', $msg);
        }

        return redirect()
            ->route('dashboard', ['audit_id' => $auditId])
            ->with('success', "Record #{$resultId} deleted (archived in the audit trail).");
    }

    /** Find one scanned result within an audit by its audit_result_id. */
    private function findResult(MiddlewareClient $client, int $auditId, int $resultId): ?array
    {
        return collect($client->scannedItems($auditId))->first(
            fn ($i) => (int) ($i['audit_result_id'] ?? 0) === $resultId
        );
    }

    /** Human label ("Location — Company") for a location id, or null. */
    private function locationLabel(array $locations, $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }
        $loc = collect($locations)->first(fn ($l) => (int) ($l['id'] ?? 0) === (int) $locationId);
        if ($loc === null) {
            return null;
        }
        $name = $loc['location_name'] ?? ('Location #'.$locationId);
        return ! empty($loc['company_name']) ? $name.' — '.$loc['company_name'] : $name;
    }

    /**
     * Groups scanned items first by site (the company of their location), then
     * by user within each site. Location comes from the item's
     * actual_location_id (falling back to the audit's location); the user is the
     * actual user, else the assigned user, with any "@device" suffix stripped.
     *
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    private function groupBySiteAndUser(array $items, array $locations, ?array $selectedAudit): array
    {
        if (empty($items)) {
            return [];
        }

        // location_id => company_name (the "site")
        $companyByLocation = [];
        foreach ($locations as $loc) {
            if (isset($loc['id'])) {
                $companyByLocation[(int) $loc['id']] = $loc['company_name'] ?? null;
            }
        }

        $auditLocationId = $selectedAudit['location_id'] ?? null;

        $groups = [];
        foreach ($items as $item) {
            $locId = $item['actual_location_id'] ?? $auditLocationId;
            $site = ($locId !== null && ! empty($companyByLocation[(int) $locId]))
                ? $companyByLocation[(int) $locId]
                : 'Unassigned site';

            $raw = trim((string) (($item['actual_user'] ?? '') ?: ($item['assigned_user'] ?? '')));
            if (str_contains($raw, '@')) {
                $raw = trim(substr($raw, 0, strpos($raw, '@'))); // drop "@DEVICE-TAG"
            }
            $user = ($raw === '' || $raw === '-') ? 'Unassigned user' : $raw;

            $groups[$site][$user][] = $item;
        }

        // Sort sites, then users within each — keeping the "Unassigned" buckets last.
        $last = fn (string $s) => str_starts_with($s, 'Unassigned');
        $cmp = function ($a, $b) use ($last) {
            if ($last($a) && ! $last($b)) return 1;
            if ($last($b) && ! $last($a)) return -1;
            return strcasecmp($a, $b);
        };
        uksort($groups, $cmp);
        foreach ($groups as &$users) {
            uksort($users, $cmp);
        }
        unset($users);

        return $groups;
    }

    /**
     * Count rows whose $key loosely equals $value (values may arrive as
     * ints or numeric strings from the JSON API).
     */
    private function countWhere(array $rows, string $key, int $value): int
    {
        return count(array_filter(
            $rows,
            fn ($row) => isset($row[$key]) && (int) $row[$key] === $value
        ));
    }
}
