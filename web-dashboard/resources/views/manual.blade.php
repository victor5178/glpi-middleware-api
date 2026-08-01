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

    {{-- GLPI asset lookup (find the asset, then it prefills the tag below) --}}
    <form method="get" action="{{ route('manual.create') }}" class="filter" style="gap:10px;">
        <div class="field" style="flex:1;">
            <label for="q">Find asset in GLPI (asset id, serial or user)</label>
            <input class="input" id="q" name="q" value="{{ $q }}" placeholder="e.g. SN-ABC-999, victor.t, 1234" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary">Query</button>
        @if ($q !== '')
            <a class="btn btn-ghost" href="{{ route('manual.create') }}">Clear</a>
        @endif
    </form>

    @if ($q !== '')
        @if ($glpiError)
            <div class="alert alert-warn">{{ $glpiError }}</div>
        @elseif (empty($glpiResults))
            <div class="alert alert-muted">No GLPI assets matched “{{ $q }}”.</div>
        @else
            <div class="grid" style="margin-bottom:22px;">
                @foreach ($glpiResults as $r)
                    @php $tag = $r['otherserial'] ?: ($r['serial'] ?: ''); @endphp
                    <div class="asset" style="cursor:default;">
                        <div class="body">
                            <div class="row1">
                                <span class="name">{{ $r['name'] ?: ('#'.$r['id']) }}</span>
                                <span class="pill pill-muted">{{ $r['type'] }}</span>
                            </div>
                            <span class="sub">Serial: {{ $r['serial'] ?: '—' }}</span>
                            <span class="sub">Asset tag: {{ $r['otherserial'] ?: '—' }}</span>
                            <span class="sub user">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                {{ $r['contact'] ?: '—' }}
                            </span>
                            <span class="meta">GLPI ID: {{ $r['id'] }}</span>
                            <a class="btn btn-primary" style="margin-top:10px;align-self:flex-start;"
                               href="{{ route('manual.create', array_filter([
                                   'asset_tag'     => $tag,
                                   'glpi_id'       => $r['id'],
                                   'serial_number' => $r['serial'],
                                   'category'      => $r['type'],
                                   'model'         => $r['name'],
                                   'assigned_user' => $r['contact'],
                               ], fn ($v) => $v !== null && $v !== '')) }}">Use this asset</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    <form method="post" action="{{ route('manual.store') }}" enctype="multipart/form-data" id="auditForm">
        @csrf

        {{-- GLPI asset details carried over from the search, so submitting can
             create/update the asset in the inventory (like the mobile scan). --}}
        <input type="hidden" name="glpi_id" value="{{ old('glpi_id', request('glpi_id')) }}">
        <input type="hidden" name="serial_number" value="{{ old('serial_number', request('serial_number')) }}">
        <input type="hidden" name="category" value="{{ old('category', request('category')) }}">
        <input type="hidden" name="model" value="{{ old('model', request('model')) }}">
        <input type="hidden" name="assigned_user" value="{{ old('assigned_user', request('assigned_user')) }}">

        @if (old('glpi_id', request('glpi_id')) || old('serial_number', request('serial_number')))
            <div class="alert alert-muted" style="margin-bottom:16px;">
                Linked to GLPI asset
                @if (old('model', request('model')))<strong>{{ old('model', request('model')) }}</strong>@endif
                @if (old('serial_number', request('serial_number')))· Serial {{ old('serial_number', request('serial_number')) }}@endif
                @if (old('category', request('category')))· {{ old('category', request('category')) }}@endif.
                It will be added to the inventory on submit.
            </div>
        @endif

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
                        <input class="input" name="checked_by"
                               value="{{ session('glpi_user') }}"
                               readonly
                               title="Set from your login"
                               style="background:#eef1f5;color:var(--muted);cursor:not-allowed;" />
                    </div>

                    <div class="form-field">
                        <label>Actual user</label>
                        <input class="input" name="actual_user" value="{{ old('actual_user', request('assigned_user')) }}" placeholder="If different from assigned" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                @php
                    // Checklist items depend on the asset's category (from the GLPI
                    // "Use this asset" step). Unknown category → the default list.
                    $catKey = strtolower(trim((string) old('category', request('category'))));
                    $cfg = config('checklist') ?? [];
                    $checks = $cfg['by_category'][$catKey] ?? $cfg['default'] ?? ['is_physical_good' => 'Physical Condition'];
                @endphp
                <label class="check" style="margin-bottom:10px;">
                    <input type="checkbox" name="asset_found" value="1" @checked(old('asset_found', '1') == '1')>
                    Asset found on site
                </label>
                <div class="section-label" style="margin-top:0;">
                    Checklist @if($catKey !== '')<span class="pill pill-muted" style="margin-left:6px;">{{ ucfirst($catKey) }}</span>@endif
                </div>
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
                    <textarea class="textarea" name="additional_info" rows="4" placeholder="Multi-line notes are supported…">{{ old('additional_info') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Photos</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <label class="btn btn-primary" style="cursor:pointer;">
                        📷 Take photo
                        <input type="file" id="photoCapture" accept="image/*" capture="environment" hidden>
                    </label>
                    <label class="btn btn-ghost" style="cursor:pointer;">
                        Choose files
                        <input type="file" name="photos[]" id="photoInput" accept="image/*" multiple hidden>
                    </label>
                </div>
                <p style="color:var(--muted);font-size:.82rem;margin:8px 0 0;">Photos are compressed automatically before upload. Take a photo or add files; hover a photo to zoom, ✕ to remove.</p>
                <div id="photoPreview" class="edit-photos" style="margin-top:12px;"></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">Submit audit</button>
    </form>

    {{-- Duplicate-asset confirmation dialog --}}
    <div id="dupModal" class="modal-overlay" style="display:none;">
        <div class="modal-card">
            <h3 style="margin:0 0 8px;">Duplicate asset</h3>
            <p id="dupMsg" style="margin:0;color:var(--muted);"></p>
            <div class="modal-actions">
                <button type="button" id="dupCancel" class="btn btn-ghost">Cancel</button>
                <button type="button" id="dupContinue" class="btn btn-primary">Continue &amp; overwrite</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById('auditForm');
        var modal = document.getElementById('dupModal');
        var checkUrl = @json(route('manual.check'));
        var proceed = false; // set once the user has confirmed (or no dup)

        form.addEventListener('submit', function (e) {
            if (proceed) return; // already cleared — let it submit

            var auditEl = form.querySelector('[name="audit_id"]');
            var tagEl = form.querySelector('[name="asset_tag"]');
            var auditId = auditEl ? auditEl.value : '';
            var assetTag = tagEl ? tagEl.value.trim() : '';
            if (!auditId || !assetTag) return; // let server-side validation handle empties

            e.preventDefault();
            var url = checkUrl + (checkUrl.indexOf('?') === -1 ? '?' : '&') +
                'audit_id=' + encodeURIComponent(auditId) +
                '&asset_tag=' + encodeURIComponent(assetTag);

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.duplicate) {
                        var msg = 'This asset was already recorded for this audit';
                        if (d.checked_by) msg += ' by ' + d.checked_by;
                        if (d.checked_at) msg += ' on ' + d.checked_at;
                        msg += '. Continue and overwrite the existing record?';
                        document.getElementById('dupMsg').textContent = msg;
                        modal.style.display = 'flex';
                    } else {
                        proceed = true;
                        form.submit();
                    }
                })
                .catch(function () { proceed = true; form.submit(); }); // fail open
        });

        document.getElementById('dupCancel').addEventListener('click', function () {
            modal.style.display = 'none'; // stay on the page, inputs untouched
        });
        document.getElementById('dupContinue').addEventListener('click', function () {
            proceed = true;
            modal.style.display = 'none';
            form.submit();
        });
    })();
    </script>

    {{-- Camera capture + client-side compression + removable previews --}}
    <script src="/js/photo-input.js"></script>
    <script>
        PhotoInput.enhance({
            input: document.getElementById('photoInput'),
            captureInput: document.getElementById('photoCapture'),
            preview: document.getElementById('photoPreview'),
            maxEdge: 2000, quality: 0.7
        });
    </script>

@endsection
