<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Report types this page can render. */
    public const TYPES = [
        'scanned' => 'List of assets scanned',
        'review' => 'Review audit (checklist)',
        'company' => 'By company — assets scanned',
    ];

    public function index(Request $request, MiddlewareClient $client)
    {
        $audits = $client->audits();
        $auditId = (int) $request->query('audit_id', 0);
        $type = (string) $request->query('type', 'scanned');
        if (! array_key_exists($type, self::TYPES)) {
            $type = 'scanned';
        }
        $company = trim((string) $request->query('company', ''));
        $withPhotos = $request->query('photos', '1') !== '0';
        $ran = $auditId > 0 && $request->has('audit_id');

        // location_id => company (the "site")
        $locations = $client->locations();
        $companyByLoc = [];
        $companyList = [];
        foreach ($locations as $l) {
            if (isset($l['id'])) {
                $companyByLoc[(int) $l['id']] = $l['company_name'] ?? null;
                if (! empty($l['company_name'])) {
                    $companyList[$l['company_name']] = true;
                }
            }
        }
        $companyList = array_keys($companyList);
        sort($companyList);

        $selectedAudit = collect($audits)->firstWhere('id', $auditId);
        $auditLocId = $selectedAudit['location_id'] ?? null;

        $items = [];
        $byCompany = [];
        $stats = ['total' => 0, 'found' => 0, 'missing' => 0, 'with_photo' => 0];

        if ($ran) {
            foreach ($client->scannedItems($auditId) as $it) {
                $locId = $it['actual_location_id'] ?? $auditLocId;
                $comp = ($locId !== null && ! empty($companyByLoc[(int) $locId]))
                    ? $companyByLoc[(int) $locId] : 'Unassigned site';
                $it['company'] = $comp;
                if ($company !== '' && $comp !== $company) {
                    continue;
                }
                $items[] = $it;
            }

            usort($items, function ($a, $b) {
                return [$a['company'], (string) ($a['asset_tag'] ?? '')]
                    <=> [$b['company'], (string) ($b['asset_tag'] ?? '')];
            });

            foreach ($items as $it) {
                $found = (int) ($it['asset_found'] ?? 0) === 1;
                $hasPhoto = ! empty($it['img_dir']);
                $stats['total']++;
                $found ? $stats['found']++ : $stats['missing']++;
                if ($hasPhoto) $stats['with_photo']++;

                $c = $it['company'];
                if (! isset($byCompany[$c])) {
                    $byCompany[$c] = ['items' => [], 'found' => 0, 'missing' => 0, 'with_photo' => 0];
                }
                $byCompany[$c]['items'][] = $it;
                $found ? $byCompany[$c]['found']++ : $byCompany[$c]['missing']++;
                if ($hasPhoto) $byCompany[$c]['with_photo']++;
            }
            ksort($byCompany);
        }

        return view('report', [
            'audits' => $audits,
            'auditId' => $auditId,
            'type' => $type,
            'types' => self::TYPES,
            'company' => $company,
            'withPhotos' => $withPhotos,
            'companyList' => $companyList,
            'ran' => $ran,
            'selectedAudit' => $selectedAudit,
            'items' => $items,
            'byCompany' => $byCompany,
            'stats' => $stats,
            'error' => $client->lastError,
            'generatedBy' => $request->session()->get('glpi_user'),
            'client' => $client,
        ]);
    }
}
