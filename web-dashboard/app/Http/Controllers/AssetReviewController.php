<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use App\Services\MiddlewareClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AssetReviewController extends Controller
{
    /** Category label => GLPI item types it maps to. */
    public const CATEGORIES = [
        'Computer' => ['Computer'],
        'Monitor' => ['Monitor'],
        'Network' => ['NetworkEquipment'],
        'Peripheral' => ['Peripheral'],
        'Printer' => ['Printer'],
    ];

    /**
     * Reconcile GLPI inventory against what has actually been checked (submitted)
     * in an audit — filterable by scanned site, GLPI site and category.
     */
    public function index(Request $request, MiddlewareClient $mw, GlpiClient $glpi)
    {
        $audits = $mw->audits();
        $auditId = (int) $request->query('audit_id', 0);
        $filter = trim((string) $request->query('filter', ''));
        $scannedSite = trim((string) $request->query('scanned_site', ''));
        $glpiSite = trim((string) $request->query('glpi_site', ''));
        $categories = array_values(array_intersect(
            (array) $request->query('categories', []),
            array_keys(self::CATEGORIES)
        ));
        $ran = $request->has('audit_id') && $auditId > 0;

        // Middleware locations → company (the scanned "site").
        $locations = $mw->locations();
        $companyByLoc = [];
        $scannedSites = [];
        foreach ($locations as $l) {
            if (isset($l['id'])) {
                $companyByLoc[(int) $l['id']] = $l['company_name'] ?? null;
                if (! empty($l['company_name'])) {
                    $scannedSites[$l['company_name']] = true;
                }
            }
        }
        $scannedSites = array_keys($scannedSites);
        sort($scannedSites);

        $checked = [];
        $notChecked = [];
        $glpiError = null;
        $glpiCount = 0;
        $glpiSites = [];

        if ($ran) {
            $username = $request->session()->get('glpi_user');
            $password = null;
            if ($enc = $request->session()->get('glpi_pw')) {
                try {
                    $password = Crypt::decryptString($enc);
                } catch (\Throwable $e) {
                    $password = null;
                }
            }
            if (($password === null || $password === '') && ! $glpi->isConfigured()) {
                $request->session()->forget(['glpi_user', 'glpi_pw']);
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->with('error', 'Please sign in again to review assets (your session predates a security update).');
            }

            // Map selected categories → GLPI item types.
            $types = [];
            foreach ($categories as $c) {
                $types = array_merge($types, self::CATEGORIES[$c]);
            }
            $types = array_values(array_unique($types));

            // Only Active GLPI assets, filtered by category.
            $glpiAssets = $glpi->listAssets($username, $password, $filter, true, $types);
            $glpiError = $glpi->lastError;

            // Distinct GLPI site (location) values for the dropdown.
            $glpiSites = collect($glpiAssets)
                ->pluck('location')->filter(fn ($v) => is_string($v) && $v !== '')
                ->unique()->sort()->values()->all();

            // Filter GLPI assets to the chosen GLPI site.
            if ($glpiSite !== '') {
                $needle = mb_strtolower($glpiSite);
                $glpiAssets = array_values(array_filter(
                    $glpiAssets,
                    fn ($a) => is_string($a['location'] ?? null) && str_contains(mb_strtolower($a['location']), $needle)
                ));
            }
            $glpiCount = count($glpiAssets);

            // Index the audit's submitted items (optionally scoped to a scanned site).
            $auditLocId = collect($audits)->firstWhere('id', $auditId)['location_id'] ?? null;
            $bySerial = [];
            foreach ($mw->scannedItems($auditId) as $s) {
                $locId = $s['actual_location_id'] ?? $auditLocId;
                $comp = ($locId !== null && ! empty($companyByLoc[(int) $locId]))
                    ? $companyByLoc[(int) $locId] : 'Unassigned site';
                if ($scannedSite !== '' && $comp !== $scannedSite) {
                    continue;
                }
                if (! empty($s['serial_number'])) {
                    $bySerial[$this->norm($s['serial_number'])] = $s;
                }
            }

            foreach ($glpiAssets as $a) {
                // Compare by serial number only.
                $serialKey = $this->norm((string) ($a['serial'] ?? ''));
                $match = ($serialKey !== '' && isset($bySerial[$serialKey])) ? $bySerial[$serialKey] : null;

                if ($match !== null) {
                    $checked[] = ['glpi' => $a, 'sub' => $match];
                } else {
                    $notChecked[] = $a;
                }
            }
        }

        return view('asset-review', [
            'audits' => $audits,
            'auditId' => $auditId,
            'filter' => $filter,
            'scannedSite' => $scannedSite,
            'glpiSite' => $glpiSite,
            'categories' => $categories,
            'categoryList' => array_keys(self::CATEGORIES),
            'scannedSites' => $scannedSites,
            'glpiSites' => $glpiSites,
            'ran' => $ran,
            'checked' => $checked,
            'notChecked' => $notChecked,
            'glpiCount' => $glpiCount,
            'glpiError' => $glpiError,
            'error' => $mw->lastError,
        ]);
    }

    /** Normalize a serial/tag for matching (trim, lower-case, strip spaces). */
    private function norm(string $v): string
    {
        return preg_replace('/\s+/', '', mb_strtolower(trim($v)));
    }
}
