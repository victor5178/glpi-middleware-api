<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use App\Services\MiddlewareClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AssetReviewController extends Controller
{
    /**
     * Reconcile GLPI inventory against what has actually been checked (submitted)
     * in an audit: which GLPI assets are covered ("found") and which are not.
     */
    public function index(Request $request, MiddlewareClient $mw, GlpiClient $glpi)
    {
        $audits = $mw->audits();
        $auditId = (int) $request->query('audit_id', 0);
        $filter = trim((string) $request->query('filter', ''));
        $ran = $request->has('audit_id') && $auditId > 0;

        $checked = [];
        $notChecked = [];
        $glpiError = null;
        $glpiCount = 0;

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

            $glpiAssets = $glpi->listAssets($username, $password, $filter);
            $glpiError = $glpi->lastError;
            $glpiCount = count($glpiAssets);

            // Index the audit's submitted items by normalized serial and asset tag.
            $bySerial = [];
            $byTag = [];
            foreach ($mw->scannedItems($auditId) as $s) {
                if (! empty($s['serial_number'])) {
                    $bySerial[$this->norm($s['serial_number'])] = $s;
                }
                if (! empty($s['asset_tag'])) {
                    $byTag[$this->norm($s['asset_tag'])] = $s;
                }
            }

            foreach ($glpiAssets as $a) {
                $serialKey = $this->norm((string) ($a['serial'] ?? ''));
                $tagKey = $this->norm((string) ($a['otherserial'] ?? ''));
                $match = ($serialKey !== '' && isset($bySerial[$serialKey])) ? $bySerial[$serialKey]
                    : (($tagKey !== '' && isset($byTag[$tagKey])) ? $byTag[$tagKey] : null);

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
