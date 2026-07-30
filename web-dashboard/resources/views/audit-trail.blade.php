@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')

    <div class="page-head">
        <h1 class="page-title">Audit Trail</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Every data change and deletion is logged here. Deleted records are archived
        with a full snapshot, so you can always see exactly what was removed.
    </p>

    @if ($error)
        <div class="alert alert-muted">{{ $error }}</div>
    @endif

    <form method="get" action="{{ route('audit-trail') }}" class="filter" style="gap:8px;">
        <div class="field">
            <label for="action">Show</label>
            <select class="select" id="action" name="action" onchange="this.form.submit()">
                <option value="" @selected($action === '')>Everything</option>
                <option value="update" @selected($action === 'update')>Changes only</option>
                <option value="delete" @selected($action === 'delete')>Deletions only</option>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-primary">Filter</button></noscript>
    </form>

    @if (empty($entries))
        <div class="alert alert-muted" style="margin-top:16px;">No audit-trail entries yet.</div>
    @else
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px;">
            @foreach ($entries as $e)
                @php
                    $isDelete = ($e['action'] ?? '') === 'delete';
                    $when = ! empty($e['created_at'])
                        ? \Illuminate\Support\Carbon::parse($e['created_at'], 'UTC')->timezone('Asia/Kuching')->format('Y-m-d H:i')
                        : '—';
                    $changes = is_array($e['changes'] ?? null) ? $e['changes'] : [];
                    $snapshot = is_array($e['snapshot'] ?? null) ? $e['snapshot'] : [];
                @endphp
                <div class="card">
                    <div class="card-body" style="padding:14px 16px;">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span class="pill {{ $isDelete ? 'pill-danger' : 'pill-success' }}">{{ $isDelete ? 'Deleted' : 'Changed' }}</span>
                            <span style="font-weight:600;">{{ str_replace('_', ' ', $e['entity_type'] ?? 'record') }}@if(!empty($e['entity_id'])) #{{ $e['entity_id'] }}@endif</span>
                            <span class="meta" style="margin-left:auto;color:var(--muted);font-size:.82rem;">
                                {{ $e['actor'] ?: 'unknown' }} · {{ $when }}
                            </span>
                        </div>

                        @if (!empty($e['summary']))
                            <div style="margin-top:6px;color:var(--muted);">{{ $e['summary'] }}</div>
                        @endif

                        @if (!empty($changes))
                            <table class="kv" style="margin-top:10px;">
                                <thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>
                                <tbody>
                                    @foreach ($changes as $field => $c)
                                        <tr>
                                            <td>{{ str_replace('_', ' ', $field) }}</td>
                                            <td style="color:var(--danger,#b91c1c);">{{ is_scalar($c['old'] ?? null) ? ($c['old'] === null ? '—' : $c['old']) : json_encode($c['old'] ?? null) }}</td>
                                            <td style="color:var(--success,#15803d);">{{ is_scalar($c['new'] ?? null) ? ($c['new'] === null ? '—' : $c['new']) : json_encode($c['new'] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        @if ($isDelete && !empty($snapshot))
                            <details style="margin-top:10px;">
                                <summary style="cursor:pointer;color:var(--brand);font-weight:600;">View deleted data</summary>
                                <table class="kv" style="margin-top:8px;">
                                    @foreach ($snapshot as $k => $v)
                                        <tr><th>{{ str_replace('_', ' ', $k) }}</th><td>{{ is_scalar($v) ? ($v === null ? '—' : $v) : json_encode($v) }}</td></tr>
                                    @endforeach
                                </table>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection
