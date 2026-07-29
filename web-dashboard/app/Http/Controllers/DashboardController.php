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

        $groups = $this->groupByCompany($items, $client->locations(), $selectedAudit);

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

        // All photos for this record (there may be more than one).
        $images = $client->resultImages($resultId);
        if (empty($images) && ! empty($item['img_dir'])) {
            $images = [$client->imageUrl($resultId)];
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
        ]);
    }

    /** Persist edits to a scanned audit result (location, user, checklist, notes). */
    public function update(int $auditId, int $resultId, Request $request, MiddlewareClient $client)
    {
        $validated = $request->validate([
            'actual_location_id' => 'required|integer',
            'actual_user' => 'nullable|string|max:100',
            'additional_info' => 'nullable|string',
        ]);

        $flag = fn (string $key) => $request->boolean($key) ? 1 : 0;

        $payload = [
            'actual_location_id' => (int) $validated['actual_location_id'],
            'actual_user' => $validated['actual_user'] ?? null,
            'additional_info' => $validated['additional_info'] ?? null,
            'asset_found' => $flag('asset_found'),
            'is_physical_good' => $flag('is_physical_good'),
            'is_patch_latest' => $flag('is_patch_latest'),
            'is_endpoint_latest' => $flag('is_endpoint_latest'),
            'is_monitor_working' => $flag('is_monitor_working'),
            'is_ups_working' => $flag('is_ups_working'),
        ];

        $result = $client->updateResult($resultId, $payload);
        if (! $result['ok']) {
            $msg = $result['body']['message'] ?? 'Failed to update the record.';
            return back()->withInput()->with('error', $msg);
        }

        return redirect()
            ->route('scanned.show', ['auditId' => $auditId, 'resultId' => $resultId])
            ->with('success', 'Record updated.');
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
     * Groups scanned items by the company of their location.
     * Location comes from the item's actual_location_id, falling back to the
     * audit's location; company is resolved via the middleware locations list.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCompany(array $items, array $locations, ?array $selectedAudit): array
    {
        if (empty($items)) {
            return [];
        }

        // location_id => company_name
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
            $company = ($locId !== null && isset($companyByLocation[(int) $locId]))
                ? $companyByLocation[(int) $locId]
                : null;
            $company = $company ?: 'Unassigned';
            $groups[$company][] = $item;
        }

        // Sort company names alphabetically, keeping "Unassigned" last.
        uksort($groups, function ($a, $b) {
            if ($a === 'Unassigned') return 1;
            if ($b === 'Unassigned') return -1;
            return strcasecmp($a, $b);
        });

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
