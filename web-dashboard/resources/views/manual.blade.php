@extends('layouts.app')

@section('title', 'Manual audit entry')

@section('content')

    <div class="page-head">
        <h1 class="page-title">Manual audit entry</h1>
    </div>

    @if (session('error'))
        <div class="alert alert-warn">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-warn">
            <strong>Please fix:</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if ($error)
        <div class="alert alert-muted">{{ $error }}</div>
    @endif

    <form method="post" action="{{ route('manual.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Asset &amp; audit</div>

                <div class="form-grid">
                    <div class="form-field">
                        <label>Audit <span class="req">*</span></label>
                        <select class="select" name="audit_id" style="min-width:0;">
                            <option value="">Select an audit…</option>
                            @foreach ($audits as $a)
                                <option value="{{ $a['id'] }}" @selected(old('audit_id') == $a['id'])>
                                    #{{ $a['id'] }} · {{ $a['audit_name'] ?? 'Audit' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Location <span class="req">*</span></label>
                        <select class="select" name="actual_location_id" style="min-width:0;">
                            <option value="">Select a location…</option>
                            @foreach ($locations as $l)
                                <option value="{{ $l['id'] }}" @selected(old('actual_location_id') == $l['id'])>
                                    {{ $l['location_name'] ?? ('Location #'.$l['id']) }}@if(!empty($l['company_name'])) — {{ $l['company_name'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Asset tag <span class="req">*</span></label>
                        <input class="input" name="asset_tag" value="{{ old('asset_tag', request('asset_tag')) }}" placeholder="e.g. PC-00123" />
                    </div>

                    <div class="form-field">
                        <label>Checked by <span class="req">*</span></label>
                        <input class="input" name="checked_by" value="{{ old('checked_by') }}" placeholder="Your name" />
                    </div>

                    <div class="form-field">
                        <label>Actual user</label>
                        <input class="input" name="actual_user" value="{{ old('actual_user') }}" placeholder="If different from assigned" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Checklist</div>
                @php
                    $checks = [
                        'asset_found' => 'Asset physically found',
                        'is_physical_good' => 'Physical condition good',
                        'is_patch_latest' => 'OS patches up to date',
                        'is_endpoint_latest' => 'Endpoint protection up to date',
                        'is_monitor_working' => 'Monitor working',
                        'is_ups_working' => 'UPS working',
                    ];
                @endphp
                <div class="checks-grid">
                    @foreach ($checks as $name => $label)
                        <label class="check">
                            <input type="checkbox" name="{{ $name }}" value="1"
                                @checked(old($name, '1') == '1')>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label>Notes</label>
                    <textarea class="textarea" name="additional_info">{{ old('additional_info') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Photos</div>
                <div class="file-drop">
                    <input type="file" name="photos[]" accept="image/*" multiple>
                </div>
                <p style="color:var(--muted);font-size:.82rem;margin:8px 0 0;">Select one or more image files (up to 50 MB each).</p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">Submit audit</button>
    </form>

@endsection
