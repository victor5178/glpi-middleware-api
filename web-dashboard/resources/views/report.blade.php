@extends('layouts.app')

@section('title', 'Report')

@section('content')

    <div class="page-head no-print">
        <h1 class="page-title">Reports</h1>
    </div>

    @if ($error)
        <div class="alert alert-muted no-print">{{ $error }}</div>
    @endif

    {{-- Selection form --}}
    <form method="get" action="{{ route('report') }}" class="card no-print" style="margin-bottom:18px;">
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
                    <label>Site (company)</label>
                    <select class="select" name="company" style="min-width:0;">
                        <option value="">All sites</option>
                        @foreach ($companyList as $c)
                            <option value="{{ $c }}" @selected($company === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>Report</label>
                    <select class="select" name="type" style="min-width:0;">
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>Photos</label>
                    <select class="select" name="photos" style="min-width:0;">
                        <option value="1" @selected($withPhotos)>With thumbnails</option>
                        <option value="0" @selected(! $withPhotos)>Without thumbnails</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:14px;">Generate report</button>
        </div>
    </form>

    @if ($ran)
        <div class="report-toolbar no-print" style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨 Print / Save as PDF</button>
        </div>

        @php
            $when = \Illuminate\Support\Carbon::now('Asia/Kuching')->format('Y-m-d H:i');
            $yesNo = fn ($v) => (int) $v === 1 ? 'Yes' : ((int) $v === 0 ? 'No' : '—');
            $fmt = fn ($t) => ! empty($t)
                ? \Illuminate\Support\Carbon::parse($t, 'UTC')->timezone('Asia/Kuching')->format('Y-m-d H:i')
                : '—';
            $thumb = fn ($it) => ! empty($it['img_dir']) ? url('media/'.ltrim($it['img_dir'], '/')) : null;
        @endphp

        <div class="report">
            <div class="report-head">
                <div>
                    <div class="report-brand">ITD Asset Audit Report</div>
                    <div class="report-title">{{ $types[$type] }}</div>
                </div>
                <div class="report-meta">
                    <div><strong>Audit:</strong> {{ $selectedAudit['audit_name'] ?? ('#'.$auditId) }} (#{{ $auditId }})</div>
                    <div><strong>Site:</strong> {{ $company ?: 'All sites' }}</div>
                    <div><strong>Generated:</strong> {{ $when }}</div>
                    <div><strong>Prepared by:</strong> {{ $generatedBy ?: '—' }}</div>
                </div>
            </div>

            {{-- Summary --}}
            <table class="report-summary">
                <tr>
                    <td><span class="n">{{ $stats['total'] }}</span><span class="l">Scanned</span></td>
                    <td><span class="n">{{ $stats['found'] }}</span><span class="l">Found</span></td>
                    <td><span class="n">{{ $stats['missing'] }}</span><span class="l">Missing</span></td>
                    <td><span class="n">{{ $stats['with_photo'] }}</span><span class="l">With photo</span></td>
                </tr>
            </table>

            @if ($stats['total'] === 0)
                <div class="alert alert-muted">No records for this selection.</div>

            @elseif ($type === 'scanned')
                <table class="report-table">
                    <thead><tr>
                        <th>#</th>@if($withPhotos)<th>Photo</th>@endif<th>Asset tag</th><th>Serial</th><th>Model</th><th>User</th><th>Company</th><th>Found</th><th>Checked by</th><th>Checked at</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($items as $i => $it)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                @if($withPhotos)<td>@php $u = $thumb($it); @endphp @if($u)<img class="report-thumb" src="{{ $u }}" alt="">@else—@endif</td>@endif
                                <td>{{ $it['asset_tag'] ?? '—' }}</td>
                                <td>{{ $it['serial_number'] ?? '—' }}</td>
                                <td>{{ $it['model'] ?? '—' }}</td>
                                <td>{{ ($it['actual_user'] ?? '') ?: ($it['assigned_user'] ?? '—') }}</td>
                                <td>{{ $it['company'] }}</td>
                                <td>{{ (int) ($it['asset_found'] ?? 0) === 1 ? 'Found' : 'Missing' }}</td>
                                <td>{{ $it['checked_by'] ?: '—' }}</td>
                                <td>{{ $fmt($it['checked_at'] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'review')
                <table class="report-table">
                    <thead><tr>
                        <th>#</th>@if($withPhotos)<th>Photo</th>@endif<th>Asset tag</th><th>User</th><th>Found</th><th>Physical</th><th>Patches</th><th>Endpoint</th><th>Monitor</th><th>UPS</th><th>Notes</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($items as $i => $it)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                @if($withPhotos)<td>@php $u = $thumb($it); @endphp @if($u)<img class="report-thumb" src="{{ $u }}" alt="">@else—@endif</td>@endif
                                <td>{{ $it['asset_tag'] ?? '—' }}</td>
                                <td>{{ ($it['actual_user'] ?? '') ?: ($it['assigned_user'] ?? '—') }}</td>
                                <td>{{ $yesNo($it['asset_found'] ?? null) }}</td>
                                <td>{{ $yesNo($it['is_physical_good'] ?? null) }}</td>
                                <td>{{ $yesNo($it['is_patch_latest'] ?? null) }}</td>
                                <td>{{ $yesNo($it['is_endpoint_latest'] ?? null) }}</td>
                                <td>{{ $yesNo($it['is_monitor_working'] ?? null) }}</td>
                                <td>{{ $yesNo($it['is_ups_working'] ?? null) }}</td>
                                <td style="white-space:pre-wrap;">{{ $it['additional_info'] ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @elseif ($type === 'company')
                @foreach ($byCompany as $comp => $grp)
                    @php $g = $grp; $gt = count($g['items']); @endphp
                    <h3 class="report-group">{{ $comp }} <span>({{ $gt }} assets · {{ $g['found'] }} found · {{ $g['missing'] }} missing)</span></h3>
                    <table class="report-table">
                        <thead><tr>
                            <th>#</th>@if($withPhotos)<th>Photo</th>@endif<th>Asset tag</th><th>Serial</th><th>Model</th><th>User</th><th>Found</th><th>Checked at</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($g['items'] as $i => $it)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    @if($withPhotos)<td>@php $u = $thumb($it); @endphp @if($u)<img class="report-thumb" src="{{ $u }}" alt="">@else—@endif</td>@endif
                                    <td>{{ $it['asset_tag'] ?? '—' }}</td>
                                    <td>{{ $it['serial_number'] ?? '—' }}</td>
                                    <td>{{ $it['model'] ?? '—' }}</td>
                                    <td>{{ ($it['actual_user'] ?? '') ?: ($it['assigned_user'] ?? '—') }}</td>
                                    <td>{{ (int) ($it['asset_found'] ?? 0) === 1 ? 'Found' : 'Missing' }}</td>
                                    <td>{{ $fmt($it['checked_at'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endif

            <div class="report-foot">
                Generated by ITD Dashboard · {{ $when }} · {{ $generatedBy ?: '' }}
            </div>
        </div>
    @endif

@endsection
