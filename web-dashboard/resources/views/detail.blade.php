@extends('layouts.app')

@section('title', ($item['asset_tag'] ?? 'Scanned asset').' · Review')

@section('content')

    @php
        $resultId = (int) ($item['audit_result_id'] ?? 0);
        $found = (int) ($item['asset_found'] ?? 0) === 1;
        $checkedAt = \Illuminate\Support\Str::of((string) ($item['checked_at'] ?? ''))->replace('T', ' ')->limit(19, '');

        $yesNo = function ($v) {
            return match ((int) $v) {
                1 => ['Yes', 'text-bg-success'],
                0 => ['No', 'text-bg-danger'],
                default => ['—', 'text-bg-secondary'],
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

    <a href="{{ route('dashboard', ['audit_id' => $auditId]) }}" class="btn btn-sm btn-outline-secondary mb-3">
        ← Back to dashboard
    </a>

    <div class="row g-4">
        {{-- Photo --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if (! empty($item['img_dir']) && $resultId > 0)
                        <img class="detail-photo"
                             src="{{ $client->imageUrl($resultId) }}"
                             alt="Photo of {{ $item['asset_tag'] ?? 'asset' }}"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                        <div class="alert alert-secondary mb-0" style="display:none;">Photo could not be loaded.</div>
                    @else
                        <div class="alert alert-secondary mb-0">No photo attached to this record.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h4 class="mb-0">{{ $item['asset_tag'] ?? 'Asset #'.($item['asset_id'] ?? '?') }}</h4>
                        <span class="badge {{ $found ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $found ? 'Found' : 'Missing' }}
                        </span>
                    </div>
                    <table class="table table-sm mt-3 mb-0">
                        <tbody>
                            <tr><th class="text-muted fw-normal">Serial</th><td>{{ $item['serial_number'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Model</th><td>{{ $item['model'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Assigned user</th><td>{{ $item['assigned_user'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Actual user</th><td>{{ $item['actual_user'] ?: '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Location id</th><td>{{ $item['actual_location_id'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Checked by</th><td>{{ $item['checked_by'] ?: '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Checked at</th><td>{{ $checkedAt ?: '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted mb-3">Audit checklist</h6>
                    <ul class="list-group list-group-flush">
                        @foreach ($checks as $label => $value)
                            @php [$text, $cls] = $yesNo($value); @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $label }}</span>
                                <span class="badge {{ $cls }}">{{ $text }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <h6 class="text-uppercase text-muted mt-4 mb-2">Notes</h6>
                    <p class="mb-0">{{ $item['additional_info'] ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

@endsection
