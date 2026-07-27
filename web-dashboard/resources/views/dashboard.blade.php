@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    @if ($error)
        <div class="alert alert-warning" role="alert">
            <strong>Heads up:</strong> {{ $error }}
        </div>
    @endif

    {{-- Audit selector --}}
    <form method="get" action="{{ route('dashboard') }}" class="row g-2 align-items-end mb-4">
        <div class="col-sm-8 col-md-6">
            <label for="audit_id" class="form-label fw-semibold">Audit</label>
            <select class="form-select" id="audit_id" name="audit_id" onchange="this.form.submit()">
                @forelse ($audits as $audit)
                    <option value="{{ $audit['id'] }}" @selected((int) $audit['id'] === $selectedAuditId)>
                        #{{ $audit['id'] }} · {{ $audit['audit_name'] ?? 'Audit' }}
                    </option>
                @empty
                    <option value="">No audits available</option>
                @endforelse
            </select>
        </div>
        <div class="col-sm-4 col-md-3">
            <noscript><button type="submit" class="btn btn-primary w-100">View</button></noscript>
        </div>
    </form>

    @if ($selectedAudit)
        <h5 class="mb-3">
            {{ $selectedAudit['audit_name'] ?? 'Audit' }}
            <span class="text-muted">#{{ $selectedAudit['id'] }}</span>
        </h5>
    @endif

    {{-- Stat cards --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Scanned', 'value' => $stats['total'], 'cls' => 'text-primary'],
                ['label' => 'Found', 'value' => $stats['found'], 'cls' => 'text-success'],
                ['label' => 'Missing', 'value' => $stats['missing'], 'cls' => 'text-danger'],
                ['label' => 'With photo', 'value' => $stats['with_photo'], 'cls' => 'text-info'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="col-6 col-md-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="value {{ $card['cls'] }}">{{ $card['value'] }}</div>
                        <div class="label">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h6 class="text-uppercase text-muted mb-3">Scanned items</h6>

    {{-- Scanned items grid --}}
    @if (empty($items))
        <div class="alert alert-secondary">No assets have been scanned for this audit yet.</div>
    @else
        <div class="row g-3">
            @foreach ($items as $item)
                @php
                    $resultId = (int) ($item['audit_result_id'] ?? 0);
                    $found = (int) ($item['asset_found'] ?? 0) === 1;
                    $checkedAt = \Illuminate\Support\Str::of((string) ($item['checked_at'] ?? ''))->replace('T', ' ')->limit(19, '');
                @endphp
                <div class="col-12 col-sm-6 col-lg-4">
                    <a class="card-link" href="{{ route('scanned.show', ['auditId' => $selectedAuditId, 'resultId' => $resultId]) }}">
                        <div class="card card-asset border-0 shadow-sm h-100">
                            @if (! empty($item['img_dir']) && $resultId > 0)
                                <img class="thumb" loading="lazy"
                                     src="{{ $client->imageUrl($resultId) }}"
                                     alt="Photo of {{ $item['asset_tag'] ?? 'asset' }}"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="thumb-placeholder" style="display:none;">Photo unavailable</div>
                            @else
                                <div class="thumb-placeholder">No photo</div>
                            @endif
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="card-title mb-1">{{ $item['asset_tag'] ?? 'Asset #'.($item['asset_id'] ?? '?') }}</h6>
                                    <span class="badge {{ $found ? 'text-bg-success' : 'text-bg-danger' }}">
                                        {{ $found ? 'Found' : 'Missing' }}
                                    </span>
                                </div>
                                <div class="text-muted small">Serial: {{ $item['serial_number'] ?? '—' }}</div>
                                <div class="text-muted small mt-2">
                                    By {{ $item['checked_by'] ?: 'Unknown' }} · {{ $checkedAt }}
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

@endsection
