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

        return view('dashboard', [
            'audits' => $audits,
            'items' => $items,
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

        return view('detail', [
            'item' => $item,
            'auditId' => $auditId,
            'client' => $client,
        ]);
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
