@extends('layouts.app')

@section('title', 'Edit '.($item['asset_tag'] ?? 'record'))

@section('content')

    <a class="back-link" href="{{ route('scanned.show', ['auditId' => $auditId, 'resultId' => $resultId]) }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to record
    </a>

    <div class="page-head">
        <h1 class="page-title">Edit · {{ $item['asset_tag'] ?? ('Asset #'.($item['asset_id'] ?? '?')) }}</h1>
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

    <form method="post" action="{{ route('scanned.update', ['auditId' => $auditId, 'resultId' => $resultId]) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="asset_tag" value="{{ $item['asset_tag'] ?? '' }}">
        <input type="hidden" name="serial_number" value="{{ $item['serial_number'] ?? '' }}">

        <div class="card">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Asset &amp; location</div>

                {{-- Read-only asset identity (edited in GLPI, not here) --}}
                <table class="kv" style="margin-bottom:14px;">
                    <tr><th>Serial</th><td>{{ $item['serial_number'] ?? '—' }}</td></tr>
                    <tr><th>Model</th><td>{{ $item['model'] ?? '—' }}</td></tr>
                    <tr><th>Checked by</th><td>{{ $item['checked_by'] ?: '—' }}</td></tr>
                </table>

                <div class="form-grid">
                    <div class="form-field">
                        <label>Location <span class="req">*</span></label>
                        <select class="select" name="actual_location_id" style="min-width:0;">
                            <option value="">Select a location…</option>
                            @foreach ($locations as $l)
                                <option value="{{ $l['id'] }}"
                                    @selected((string) old('actual_location_id', $item['actual_location_id'] ?? '') === (string) $l['id'])>
                                    {{ $l['location_name'] ?? ('Location #'.$l['id']) }}@if(!empty($l['company_name'])) — {{ $l['company_name'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Actual user</label>
                        <input class="input" name="actual_user" value="{{ old('actual_user', $item['actual_user'] ?? '') }}" placeholder="Person using the asset" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                @php
                    $catKey = strtolower(trim((string) ($item['category'] ?? '')));
                    $cfg = config('checklist');
                    $checks = $cfg['by_category'][$catKey] ?? $cfg['default'];
                @endphp
                <label class="check" style="margin-bottom:10px;">
                    <input type="checkbox" name="asset_found" value="1" @checked((int) old('asset_found', $item['asset_found'] ?? 0) === 1)>
                    Asset found on site
                </label>
                <div class="section-label" style="margin-top:0;">
                    Checklist @if($catKey !== '')<span class="pill pill-muted" style="margin-left:6px;">{{ ucfirst($catKey) }}</span>@endif
                </div>
                <div class="checks-grid">
                    @foreach ($checks as $name => $label)
                        <label class="check">
                            <input type="checkbox" name="{{ $name }}" value="1"
                                @checked((int) old($name, $item[$name] ?? 0) === 1)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label>Notes</label>
                    <textarea class="textarea" name="additional_info" rows="4" placeholder="Multi-line notes are supported…">{{ old('additional_info', $item['additional_info'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Photos</div>

                @if (! empty($images))
                    <p style="color:var(--muted);font-size:.82rem;margin:0 0 10px;">
                        Tick a photo to remove it — removal deletes the file from the server when you save.
                        Hover a photo to zoom.
                    </p>
                    <div class="edit-photos">
                        @foreach ($images as $img)
                            <label class="edit-photo">
                                <span class="zoom-wrap">
                                    <img src="{{ url('media/'.ltrim($img['path'], '/')) }}" alt="Audit photo" loading="lazy">
                                </span>
                                <span class="edit-photo-remove">
                                    <input type="checkbox" name="remove_images[]" value="{{ $img['path'] }}">
                                    Remove
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p style="color:var(--muted);margin:0 0 10px;">No photos attached to this record.</p>
                @endif

                <div class="form-field" style="margin-top:14px;">
                    <label>Add photos</label>
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
                    <p style="color:var(--muted);font-size:.82rem;margin:8px 0 0;">New photos are compressed automatically before upload. Hover to zoom, ✕ to remove before saving.</p>
                    <div id="photoPreview" class="edit-photos" style="margin-top:12px;"></div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;align-items:center;">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a class="btn btn-ghost" href="{{ route('scanned.show', ['auditId' => $auditId, 'resultId' => $resultId]) }}">Cancel</a>
        </div>
    </form>

    {{-- Delete this record (archived to the Audit Trail) --}}
    <form method="post" action="{{ route('scanned.destroy', ['auditId' => $auditId, 'resultId' => $resultId]) }}"
          onsubmit="return confirm('Delete this record? It will be archived in the Audit Trail and can be reviewed there.');"
          style="margin-top:22px;padding-top:16px;border-top:1px solid var(--line);">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn" style="background:var(--danger,#b91c1c);color:#fff;">Delete record</button>
        <span style="color:var(--muted);font-size:.82rem;margin-left:8px;">Removes this scanned result; a full snapshot is kept in the Audit Trail.</span>
    </form>

    {{-- Camera capture + client-side compression + removable previews for new photos --}}
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
