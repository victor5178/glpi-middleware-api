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
        $items = $client->scannedItems($auditId);

        $item = collect($items)->first(
            fn ($i) => (int) ($i['audit_result_id'] ?? 0) === $resultId
        );

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
        ]);
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
