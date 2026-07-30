<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    /** Show the audit trail: data changes (updates) and deletions (archived). */
    public function index(Request $request, MiddlewareClient $client)
    {
        $action = trim((string) $request->query('action', ''));

        $filters = [];
        if (in_array($action, ['update', 'delete'], true)) {
            $filters['action'] = $action;
        }

        return view('audit-trail', [
            'entries' => $client->auditTrail($filters),
            'action' => $action,
            'error' => $client->lastError,
        ]);
    }
}
