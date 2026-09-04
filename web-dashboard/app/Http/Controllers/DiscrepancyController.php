<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use App\Services\MiddlewareClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Asset Assignment Discrepancy Review.
 *
 * Compares GLPI inventory (Input A) against site-scan results (Input B) and
 * lists assets where the registered user or location no longer matches what was
 * found on site — so IT can reconcile swaps that weren't reported.
 */
class DiscrepancyController extends Controller
{
    /** Category label => GLPI item types. */
    public const CATEGORIES = [
        'Computer' => ['Computer'],
        'Monitor' => ['Monitor'],
        'Network' => ['NetworkEquipment'],
        'Peripheral' => ['Peripheral'],
        'Printer' => ['Printer'],
    ];

    private const HEADERS = [
        'Asset Tag', 'Device Name / Hostname', 'GLPI Location', 'Actual Location (Site Scan)',
        'GLPI Registered User', 'Actual User (Site Scan)', 'Discrepancy Type', 'Recommended GLPI Update Action',
    ];

    public function index(Request $request, MiddlewareClient $mw, GlpiClient $glpi)
    {
        $audits = $mw->audits();
        $categoryList = array_keys(self::CATEGORIES);
        $categories = array_values(array_intersect((array) $request->query('categories', []), $categoryList));
        $auditId = (int) $request->query('audit_id', 0);
        $filter = trim((string) $request->query('filter', ''));
        $format = (string) $request->query('format', '');
        $ran = $request->has('audit_id') && $auditId > 0;

        $rows = [];
        $glpiError = null;
        $seen = ['glpi' => [], 'scanned' => []];
        if ($ran) {
            [$rows, $glpiError, $redirect, $seen] = $this->buildRows($request, $mw, $glpi, $auditId, $filter, $categories);
            if ($redirect) {
                return $redirect;
            }

            if ($format === 'csv') {
                return $this->downloadCsv($rows, $auditId);
            }
            if ($format === 'md') {
                return $this->downloadMarkdown($rows, $auditId);
            }
        }

        return view('discrepancy', [
            'audits' => $audits,
            'auditId' => $auditId,
            'filter' => $filter,
            'categories' => $categories,
            'categoryList' => $categoryList,
            'ran' => $ran,
            'rows' => $rows,
            'headers' => self::HEADERS,
            'markdown' => $ran ? $this->toMarkdown($rows) : '',
            'seen' => $seen,
            'glpiError' => $glpiError,
            'error' => $mw->lastError,
        ]);
    }

    /**
     * @return array{0: array<int,array<string,string>>, 1: ?string, 2: mixed, 3: array{glpi:array,scanned:array}}
     */
    private function buildRows(Request $request, MiddlewareClient $mw, GlpiClient $glpi, int $auditId, string $filter, array $categories): array
    {
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
            return [[], null, redirect()->route('login')
                ->with('error', 'Please sign in again to run this review (your session predates a security update).'),
                ['glpi' => [], 'scanned' => []]];
        }

        $types = [];
        foreach ($categories as $c) {
            $types = array_merge($types, self::CATEGORIES[$c]);
        }
        $types = array_values(array_unique($types));

        $glpiAssets = $glpi->listAssets($username, $password, $filter, true, $types);
        $glpiError = $glpi->lastError;

        // Scanned results indexed by serial / tag, with resolved site (company).
        $companyByLoc = [];
        foreach ($mw->locations() as $l) {
            if (isset($l['id'])) {
                $companyByLoc[(int) $l['id']] = $l['company_name'] ?? null;
            }
        }
        $auditLocId = collect($mw->audits())->firstWhere('id', $auditId)['location_id'] ?? null;

        $scannedSeen = [];
        $bySerial = [];
        $byTag = [];
        foreach ($mw->scannedItems($auditId) as $s) {
            $locId = $s['actual_location_id'] ?? $auditLocId;
            $s['_site'] = ($locId !== null && ! empty($companyByLoc[(int) $locId])) ? $companyByLoc[(int) $locId] : '';
            if ($s['_site'] !== '') $scannedSeen[$s['_site']] = true;
            if (! empty($s['serial_number'])) $bySerial[$this->norm($s['serial_number'])] = $s;
            if (! empty($s['asset_tag'])) $byTag[$this->norm($s['asset_tag'])] = $s;
        }

        $glpiSeen = [];
        $rows = [];
        foreach ($glpiAssets as $a) {
            if (is_string($a['location'] ?? null) && $a['location'] !== '') $glpiSeen[$a['location']] = true;
            $serialKey = $this->norm((string) ($a['serial'] ?? ''));
            $tagKey = $this->norm((string) ($a['otherserial'] ?? ''));
            $sub = ($serialKey !== '' && isset($bySerial[$serialKey])) ? $bySerial[$serialKey]
                : (($tagKey !== '' && isset($byTag[$tagKey])) ? $byTag[$tagKey] : null);
            if ($sub === null) {
                continue; // only assets actually scanned can be compared
            }

            $glpiUser = trim((string) ($a['user'] ?? ''));
            $actualUser = trim((string) ($sub['actual_user'] ?? ''));
            $glpiLoc = trim((string) ($a['location'] ?? ''));
            $actualLoc = trim((string) ($sub['_site'] ?? ''));

            $userMismatch = $actualUser !== '' && $this->normUser($actualUser) !== $this->normUser($glpiUser);
            $locMismatch = $actualLoc !== '' && $glpiLoc !== ''
                && ! $this->locMatches($glpiLoc, $actualLoc);

            if (! $userMismatch && ! $locMismatch) {
                continue;
            }

            $typeParts = [];
            $actions = [];
            if ($userMismatch) {
                $typeParts[] = 'User';
                $actions[] = 'Update GLPI user → '.($actualUser ?: '(unassign)');
            }
            if ($locMismatch) {
                $typeParts[] = 'Location';
                $actions[] = 'Update GLPI location → '.$actualLoc;
            }

            $rows[] = [
                'Asset Tag' => ($a['otherserial'] ?? '') ?: ($sub['asset_tag'] ?? '—'),
                'Device Name / Hostname' => ($a['name'] ?? '') ?: ($sub['model'] ?? '—'),
                'GLPI Location' => $glpiLoc ?: '—',
                'Actual Location (Site Scan)' => $actualLoc ?: '—',
                'GLPI Registered User' => $glpiUser ?: '—',
                'Actual User (Site Scan)' => $actualUser ?: '—',
                'Discrepancy Type' => implode(' & ', $typeParts).' mismatch',
                'Recommended GLPI Update Action' => implode('; ', $actions),
            ];
        }

        usort($rows, fn ($x, $y) => [$x['Discrepancy Type'], $x['Asset Tag']] <=> [$y['Discrepancy Type'], $y['Asset Tag']]);

        ksort($glpiSeen);
        ksort($scannedSeen);
        $seen = ['glpi' => array_keys($glpiSeen), 'scanned' => array_keys($scannedSeen)];

        return [$rows, $glpiError, null, $seen];
    }

    private function toMarkdown(array $rows): string
    {
        $esc = fn ($v) => str_replace('|', '\\|', (string) $v);
        $out = '| '.implode(' | ', self::HEADERS).' |'."\n";
        $out .= '|'.str_repeat(' --- |', count(self::HEADERS))."\n";
        foreach ($rows as $r) {
            $cells = array_map(fn ($h) => $esc($r[$h] ?? ''), self::HEADERS);
            $out .= '| '.implode(' | ', $cells).' |'."\n";
        }
        return $out;
    }

    private function downloadMarkdown(array $rows, int $auditId)
    {
        $body = "# Asset Assignment Discrepancy Review\n\nAudit #{$auditId} · generated "
            .now('Asia/Kuching')->format('Y-m-d H:i')."\n\n".$this->toMarkdown($rows);
        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="discrepancy_audit'.$auditId.'.md"',
        ]);
    }

    private function downloadCsv(array $rows, int $auditId)
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, self::HEADERS);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($h) => $r[$h] ?? '', self::HEADERS));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response("\xEF\xBB\xBF".$csv, 200, [ // BOM for Excel
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="discrepancy_audit'.$auditId.'.csv"',
        ]);
    }

    private function norm(string $v): string
    {
        return preg_replace('/\s+/', '', mb_strtolower(trim($v)));
    }

    /** User compare: lower-case, trim, drop any "@device" suffix. */
    private function normUser(string $v): string
    {
        $v = trim(mb_strtolower($v));
        if (str_contains($v, '@')) {
            $v = trim(substr($v, 0, strpos($v, '@')));
        }
        return $v === '-' ? '' : $v;
    }

    /**
     * Do a GLPI location and a scanned site refer to the same place? If BOTH are
     * mapped in config/sites.php they must share a canonical key (precise);
     * otherwise fall back to a tolerant text compare (either contains the other).
     */
    private function locMatches(string $a, string $b): bool
    {
        $ca = $this->canonicalSite($a);
        $cb = $this->canonicalSite($b);
        if ($ca !== null && $cb !== null) {
            return $ca === $cb;
        }

        $al = mb_strtolower(trim($a));
        $bl = mb_strtolower(trim($b));
        if ($al === $bl) return true;
        return $al !== '' && $bl !== '' && (str_contains($al, $bl) || str_contains($bl, $al));
    }

    /** Resolve a location/site name to its canonical key from config/sites.php. */
    private function canonicalSite(string $name): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ((array) config('sites.aliases', []) as $canonical => $aliases) {
                $map[mb_strtolower(trim($canonical))] = $canonical;
                foreach ((array) $aliases as $alias) {
                    $map[mb_strtolower(trim($alias))] = $canonical;
                }
            }
        }
        return $map[mb_strtolower(trim($name))] ?? null;
    }
}
