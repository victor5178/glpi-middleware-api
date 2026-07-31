@extends('layouts.app')

@section('title', 'Asset Assignment Discrepancy Review')

@section('content')

    <div class="page-head">
        <h1 class="page-title">Asset Assignment Discrepancy Review</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Compares <strong>GLPI</strong> inventory (Input A) against the <strong>site
        scan</strong> results (Input B) and lists assets whose <em>registered user</em>
        or <em>location</em> no longer matches what was found on site — so unreported
        swaps can be reconciled. Only Active GLPI assets that were actually scanned
        are compared.
    </p>

    @if ($error)
        <div class="alert alert-muted">{{ $error }}</div>
    @endif

    <form method="get" action="{{ route('discrepancy') }}" class="card">
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
                    <label>Keyword (optional)</label>
                    <input class="input" name="filter" value="{{ $filter }}" placeholder="e.g. site / tag / user" autocomplete="off">
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
                    <span style="color:var(--muted);font-size:.8rem;align-self:center;">(none = all)</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:14px;">Run review</button>
        </div>
    </form>

    @if ($ran)
        @if ($glpiError)
            <div class="alert alert-warn" style="margin-top:16px;">{{ $glpiError }}</div>
        @endif

        {{-- Names to help build the config/sites.php mapping --}}
        @if (! empty($seen['glpi']) || ! empty($seen['scanned']))
            <details style="margin-top:16px;">
                <summary style="cursor:pointer;color:var(--brand);font-weight:600;">Detected site names (for precise location mapping)</summary>
                <div class="card" style="margin-top:8px;"><div class="card-body">
                    <p style="color:var(--muted);font-size:.84rem;margin:0 0 10px;">
                        Map matching names to a shared key in <code>config/sites.php</code> so location
                        mismatches are precise. Names not mapped use a tolerant compare.
                    </p>
                    <div class="form-grid">
                        <div>
                            <div class="section-label" style="margin-top:0;">GLPI locations</div>
                            <ul style="margin:6px 0 0 16px;">@forelse ($seen['glpi'] as $g)<li>{{ $g }}</li>@empty<li class="muted">—</li>@endforelse</ul>
                        </div>
                        <div>
                            <div class="section-label" style="margin-top:0;">Scanned sites</div>
                            <ul style="margin:6px 0 0 16px;">@forelse ($seen['scanned'] as $s)<li>{{ $s }}</li>@empty<li class="muted">—</li>@endforelse</ul>
                        </div>
                    </div>
                </div></div>
            </details>
        @endif

        @php $q = ['audit_id' => $auditId, 'filter' => $filter, 'categories' => $categories]; @endphp

        <div style="display:flex;align-items:center;gap:10px;margin:18px 0 10px;flex-wrap:wrap;">
            <div class="section-label" style="margin:0;">{{ count($rows) }} discrepanc{{ count($rows) === 1 ? 'y' : 'ies' }} found</div>
            @if (! empty($rows))
                <div style="margin-left:auto;display:flex;gap:8px;">
                    <a class="btn btn-ghost" href="{{ route('discrepancy', $q + ['format' => 'csv']) }}">⬇ CSV</a>
                    <a class="btn btn-ghost" href="{{ route('discrepancy', $q + ['format' => 'md']) }}">⬇ Markdown</a>
                </div>
            @endif
        </div>

        @if (empty($rows))
            <div class="alert alert-success">No user/location discrepancies for this selection. 🎉</div>
        @else
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="report-table" style="min-width:900px;">
                    <thead><tr>
                        @foreach ($headers as $h)<th>{{ $h }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                @foreach ($headers as $h)
                                    <td>
                                        @if ($h === 'Discrepancy Type')
                                            <span class="pill pill-danger">{{ $r[$h] }}</span>
                                        @else
                                            {{ $r[$h] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>

            {{-- Copyable Markdown --}}
            <details style="margin-top:16px;">
                <summary style="cursor:pointer;color:var(--brand);font-weight:600;">Markdown table (copy)</summary>
                <pre style="overflow-x:auto;background:#0e1116;color:#e6edf3;padding:14px;border-radius:8px;margin-top:8px;font-size:.8rem;">{{ $markdown }}</pre>
            </details>
        @endif
    @endif

@endsection
