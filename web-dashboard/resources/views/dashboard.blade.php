@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    // SVG path for an asset category icon.
    $categoryIcon = function ($cat) {
        $c = strtolower(trim((string) $cat));
        return match (true) {
            str_contains($c, 'laptop') => 'M4 5h16v10H4z M2 19h20',
            str_contains($c, 'printer') => 'M6 9V3h12v6 M6 18H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2 M8 14h8v6H8z',
            str_contains($c, 'monitor') => 'M3 4h18v12H3z M8 20h8 M12 16v4',
            str_contains($c, 'ups') => 'M6 7h12v10H6z M9 4h6 M10 12h4',
            str_contains($c, 'peripheral') || str_contains($c, 'network') => 'M12 2a5 5 0 0 0-5 5v10a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5z M12 6v4',
            default => 'M3 4h18v12H3z M8 20h8 M12 16v4', // computer / desktop
        };
    };
    // Split "user@HOSTNAME": device name = after @, clean user = before @.
    $splitAssigned = function ($item) {
        $assigned = (string) ($item['assigned_user'] ?? '');
        $host = str_contains($assigned, '@') ? trim(substr($assigned, strpos($assigned, '@') + 1)) : null;
        $raw = ((string) ($item['actual_user'] ?? '')) ?: $assigned;
        $user = str_contains($raw, '@') ? trim(substr($raw, 0, strpos($raw, '@'))) : $raw;
        if ($user === '' || $user === '-') $user = '—';
        return [$host, $user];
    };
@endphp

    @if ($error)
        <div class="alert alert-warn"><strong>Heads up:</strong> {{ $error }}</div>
    @endif

    {{-- Audit selector --}}
    <form method="get" action="{{ route('dashboard') }}" class="filter">
        <div class="field">
            <label for="audit_id">Audit</label>
            <select class="select" id="audit_id" name="audit_id" onchange="this.form.submit()">
                @forelse ($audits as $audit)
                    <option value="{{ $audit['id'] }}" @selected((int) $audit['id'] === $selectedAuditId)>
                        #{{ $audit['id'] }} · {{ $audit['audit_name'] ?? 'Audit' }}
                    </option>
                @empty
                    <option value="">No audits available</option>
                @endforelse
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-primary">View</button></noscript>
    </form>

    <div class="page-head">
        <h1 class="page-title">
            {{ $selectedAudit['audit_name'] ?? 'Audit overview' }}
            @if ($selectedAudit)<span class="muted">#{{ $selectedAudit['id'] }}</span>@endif
        </h1>
    </div>

    {{-- Stat cards --}}
    @php
        $cards = [
            ['label' => 'Scanned',    'value' => $stats['total'],      'cls' => 'i-brand',   'icon' => 'M3 3v18h18M7 14l3-3 3 3 5-5'],
            ['label' => 'Found',      'value' => $stats['found'],      'cls' => 'i-success', 'icon' => 'M20 6L9 17l-5-5'],
            ['label' => 'Missing',    'value' => $stats['missing'],    'cls' => 'i-danger',  'icon' => 'M18 6L6 18M6 6l12 12'],
            ['label' => 'With photo', 'value' => $stats['with_photo'], 'cls' => 'i-info',    'icon' => 'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ];
    @endphp
    <div class="stats">
        @foreach ($cards as $card)
            <div class="stat">
                <span class="icon {{ $card['cls'] }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}"/></svg>
                </span>
                <div>
                    <div class="value">{{ $card['value'] }}</div>
                    <div class="label">{{ $card['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="section-label">Scanned items</div>

    @if (empty($groups))
        <div class="alert alert-muted">No assets have been scanned for this audit yet.</div>
    @else
        @foreach ($groups as $site => $usersInSite)
            @php $siteCount = collect($usersInSite)->sum(fn ($u) => count($u)); @endphp
            <div class="group-head">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M6 21V7l6-4 6 4v14M10 9h.01M14 9h.01M10 13h.01M14 13h.01M10 17h.01M14 17h.01"/></svg>
                <span class="group-name">{{ $site }}</span>
                <span class="group-count">{{ $siteCount }}</span>
            </div>

            @foreach ($usersInSite as $user => $userItems)
                <details class="user-group">
                    <summary>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="user-name">{{ $user }}</span>
                        <span class="group-count">{{ count($userItems) }}</span>
                    </summary>
                    <div class="grid">
                        @foreach ($userItems as $item)
                            @php
                                $resultId = (int) ($item['audit_result_id'] ?? 0);
                                $found = (int) ($item['asset_found'] ?? 0) === 1;
                                $checkedAt = ! empty($item['checked_at'])
                                    ? \Illuminate\Support\Carbon::parse($item['checked_at'], 'UTC')->timezone('Asia/Kuching')->format('Y-m-d H:i')
                                    : '';
                                $hasPhoto = ! empty($item['img_dir']) && $resultId > 0;
                                [$hostName, $cardUser] = $splitAssigned($item);
                                $deviceName = $hostName ?: ($item['asset_tag'] ?? ('Asset #'.($item['asset_id'] ?? '?')));
                                $category = $item['category'] ?? '';
                            @endphp
                            <a class="asset" href="{{ route('scanned.show', ['auditId' => $selectedAuditId, 'resultId' => $resultId]) }}">
                                <div class="thumb-wrap">
                                    @if ($hasPhoto)
                                        <img loading="lazy" src="{{ url('media/'.ltrim($item['img_dir'], '/')) }}"
                                             alt="Photo of {{ $item['asset_tag'] ?? 'asset' }}"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                        <div class="thumb-empty" style="display:none;">Photo unavailable</div>
                                    @else
                                        <div class="thumb-empty">
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/></svg>
                                            No photo
                                        </div>
                                    @endif
                                </div>
                                <div class="body">
                                    <div class="row1">
                                        <span class="name">{{ $deviceName }}</span>
                                        <span class="cat-icon {{ $found ? 'cat-found' : 'cat-missing' }}" title="{{ $category ?: 'Asset' }}{{ $found ? ' · Found' : ' · Missing' }}">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $categoryIcon($category) }}"/></svg>
                                        </span>
                                    </div>
                                    <span class="sub">{{ $item['model'] ?? 'Unknown model' }}</span>
                                    <span class="sub user">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        {{ $cardUser }}
                                    </span>
                                    @if ($hostName)<span class="sub">Tag: {{ $item['asset_tag'] ?? '—' }}</span>@endif
                                    <span class="sub">Serial: {{ $item['serial_number'] ?? '—' }}</span>
                                    <span class="meta">{{ $item['checked_by'] ?: 'Unknown' }} · {{ $checkedAt }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @endforeach
    @endif

@endsection
