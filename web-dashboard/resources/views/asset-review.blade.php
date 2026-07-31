@extends('layouts.app')

@section('title', 'Asset Review')

@section('content')

    <div class="page-head">
        <h1 class="page-title">Asset Review</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Compares <strong>Active</strong> GLPI assets against what has been checked in
        an audit — so you can see which are done and which still need auditing
        (decommissioned / spare / disposed excluded). Scope by scanned site, GLPI
        site and category. Tip: run once, then pick the GLPI site from the list and
        compare again.
    </p>

    @if ($error)
        <div class="alert alert-muted">{{ $error }}</div>
    @endif

    <form method="get" action="{{ route('asset-review') }}" class="card">
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label>Audit <span class="req">*</span></label>
                    <select class="select" name="audit_id" style="min-width:0;">
                        <option value="">Select an audit…</option>
                        @foreach ($audits as $a)
                            <option value="{{ $a['id'] }}" @selected($auditId === (int) $a['id'])>
                                #{{ $a['id'] }} · {{ $a['audit_name'] ?? 'Audit' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>Scanned site (audit location)</label>
                    <select class="select" name="scanned_site" style="min-width:0;">
                        <option value="">All scanned sites</option>
                        @foreach ($scannedSites as $s)
                            <option value="{{ $s }}" @selected($scannedSite === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>GLPI site (location)</label>
                    <select class="select" name="glpi_site" style="min-width:0;">
                        <option value="">All GLPI sites</option>
                        {{-- Populated from the last comparison; keeps the current pick available --}}
                        @php $opts = collect($glpiSites); if ($glpiSite !== '' && ! $opts->contains($glpiSite)) $opts = $opts->push($glpiSite); @endphp
                        @foreach ($opts as $s)
                            <option value="{{ $s }}" @selected($glpiSite === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>Keyword (optional)</label>
                    <input class="input" name="filter" value="{{ $filter }}" placeholder="e.g. tag / serial / user" autocomplete="off">
                </div>
            </div>

            <div class="form-field" style="margin-top:12px;">
                <label>Category</label>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;">
                    @foreach ($categoryList as $c)
                        <label class="check" style="display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="categories[]" value="{{ $c }}" @checked(in_array($c, $categories, true))>
                            {{ $c }}
                        </label>
                    @endforeach
                    <span style="color:var(--muted);font-size:.8rem;align-self:center;">(none = all categories)</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:14px;">Compare</button>
        </div>
    </form>

    @if ($ran)
        @if ($glpiError)
            <div class="alert alert-warn" style="margin-top:16px;">{{ $glpiError }}</div>
        @endif

        @php
            $done = count($checked);
            $todo = count($notChecked);
            $total = $done + $todo;
            $pct = $total > 0 ? round($done / $total * 100) : 0;
        @endphp

        <div class="stats" style="margin-top:18px;">
            <div class="stat"><div><div class="value">{{ $total }}</div><div class="label">GLPI assets (filtered)</div></div></div>
            <div class="stat"><span class="icon i-success"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span><div><div class="value">{{ $done }}</div><div class="label">Checked</div></div></div>
            <div class="stat"><span class="icon i-danger"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></span><div><div class="value">{{ $todo }}</div><div class="label">Not checked</div></div></div>
            <div class="stat"><div><div class="value">{{ $pct }}%</div><div class="label">Coverage</div></div></div>
        </div>

        {{-- Not checked first — that's the actionable list --}}
        <div class="group-head" style="margin-top:22px;">
            <span class="group-name">Not checked yet</span>
            <span class="group-count">{{ $todo }}</span>
        </div>
        @if ($todo === 0)
            <div class="alert alert-success">Every GLPI asset in this scope has been checked. 🎉</div>
        @else
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="kv" style="min-width:640px;">
                    <thead><tr>
                        <th>Asset</th><th>Type</th><th>Serial</th><th>Asset tag</th><th>User</th><th>Location</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($notChecked as $a)
                            <tr>
                                <td>{{ $a['name'] ?: ('#'.$a['id']) }}</td>
                                <td>{{ $a['type'] }}</td>
                                <td>{{ $a['serial'] ?: '—' }}</td>
                                <td>{{ $a['otherserial'] ?: '—' }}</td>
                                <td>{{ $a['contact'] ?: '—' }}</td>
                                <td>{{ $a['location'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif

        <div class="group-head" style="margin-top:22px;">
            <span class="group-name">Checked</span>
            <span class="group-count">{{ $done }}</span>
        </div>
        @if ($done === 0)
            <div class="alert alert-muted">No GLPI asset in this scope has been checked in this audit yet.</div>
        @else
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="kv" style="min-width:640px;">
                    <thead><tr>
                        <th>Asset</th><th>Serial</th><th>Found?</th><th>Checked by</th><th>Checked at</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($checked as $row)
                            @php
                                $a = $row['glpi']; $s = $row['sub'];
                                $found = (int) ($s['asset_found'] ?? 0) === 1;
                                $when = ! empty($s['checked_at'])
                                    ? \Illuminate\Support\Carbon::parse($s['checked_at'], 'UTC')->timezone('Asia/Kuching')->format('Y-m-d H:i')
                                    : '—';
                                $rid = (int) ($s['audit_result_id'] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    @if ($rid > 0)
                                        <a href="{{ route('scanned.show', ['auditId' => $auditId, 'resultId' => $rid]) }}">{{ $a['name'] ?: ('#'.$a['id']) }}</a>
                                    @else
                                        {{ $a['name'] ?: ('#'.$a['id']) }}
                                    @endif
                                </td>
                                <td>{{ $a['serial'] ?: '—' }}</td>
                                <td><span class="pill {{ $found ? 'pill-success' : 'pill-danger' }}">{{ $found ? 'Found' : 'Missing' }}</span></td>
                                <td>{{ $s['checked_by'] ?: '—' }}</td>
                                <td>{{ $when }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif
    @endif

@endsection
