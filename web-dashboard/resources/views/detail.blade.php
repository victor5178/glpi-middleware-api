@extends('layouts.app')

@section('title', ($item['asset_tag'] ?? 'Scanned asset').' · Review')

@section('content')

    @php
        $resultId = (int) ($item['audit_result_id'] ?? 0);
        $found = (int) ($item['asset_found'] ?? 0) === 1;
        $hasPhoto = ! empty($item['img_dir']) && $resultId > 0;
        $checkedAt = \Illuminate\Support\Str::of((string) ($item['checked_at'] ?? ''))->replace('T', ' ')->limit(19, '');

        $yesNo = function ($v) {
            return match ((int) $v) {
                1 => ['Yes', 'pill-success'],
                0 => ['No', 'pill-danger'],
                default => ['—', 'pill-muted'],
            };
        };
        $checks = [
            'Asset physically found' => $item['asset_found'] ?? null,
            'Physical condition good' => $item['is_physical_good'] ?? null,
            'OS patches up to date' => $item['is_patch_latest'] ?? null,
            'Endpoint protection up to date' => $item['is_endpoint_latest'] ?? null,
            'Monitor working' => $item['is_monitor_working'] ?? null,
            'UPS working' => $item['is_ups_working'] ?? null,
        ];
    @endphp

    <a class="back-link" href="{{ route('dashboard', ['audit_id' => $auditId]) }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to dashboard
    </a>

    <div class="page-head">
        <h1 class="page-title">
            {{ $item['asset_tag'] ?? 'Asset #'.($item['asset_id'] ?? '?') }}
            <span class="pill {{ $found ? 'pill-success' : 'pill-danger' }}"><span class="dot"></span>{{ $found ? 'Found' : 'Missing' }}</span>
        </h1>
    </div>

    <div class="detail-grid">
        {{-- Photos (one or more) --}}
        <div class="card">
            <div class="card-body">
                @if (! empty($images))
                    <div class="photo-frame">
                        <img id="mainPhoto" src="{{ $images[0] }}"
                             alt="Photo of {{ $item['asset_tag'] ?? 'asset' }}">
                    </div>

                    @if (count($images) > 1)
                        <div class="thumbs">
                            @foreach ($images as $i => $url)
                                <img class="thumb-sm {{ $i === 0 ? 'active' : '' }}" src="{{ $url }}"
                                     alt="Photo {{ $i + 1 }}"
                                     onclick="document.getElementById('mainPhoto').src=this.src;document.querySelectorAll('.thumb-sm').forEach(function(t){t.classList.remove('active');});this.classList.add('active');">
                            @endforeach
                        </div>
                        <div class="photo-count">{{ count($images) }} photos</div>
                    @endif
                @else
                    <div class="alert alert-muted" style="margin:0;">No photo attached to this record.</div>
                @endif
            </div>
        </div>

        {{-- Info + checklist --}}
        <div>
            <div class="card" style="margin-bottom:22px;">
                <div class="card-body">
                    <div class="section-label" style="margin-top:0;">Asset</div>
                    <table class="kv">
                        <tr><th>Serial</th><td>{{ $item['serial_number'] ?? '—' }}</td></tr>
                        <tr><th>Model</th><td>{{ $item['model'] ?? '—' }}</td></tr>
                        <tr><th>Assigned user</th><td>{{ $item['assigned_user'] ?? '—' }}</td></tr>
                        <tr><th>Actual user</th><td>{{ $item['actual_user'] ?: '—' }}</td></tr>
                        <tr><th>Location id</th><td>{{ $item['actual_location_id'] ?? '—' }}</td></tr>
                        <tr><th>Checked by</th><td>{{ $item['checked_by'] ?: '—' }}</td></tr>
                        <tr><th>Checked at</th><td>{{ $checkedAt ?: '—' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="section-label" style="margin-top:0;">Audit checklist</div>
                    <ul class="checks">
                        @foreach ($checks as $label => $value)
                            @php [$text, $cls] = $yesNo($value); @endphp
                            <li><span>{{ $label }}</span><span class="pill {{ $cls }}">{{ $text }}</span></li>
                        @endforeach
                    </ul>

                    <div class="section-label">Notes</div>
                    <p style="margin:0;">{{ $item['additional_info'] ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

@endsection
