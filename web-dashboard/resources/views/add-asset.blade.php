@extends('layouts.app')

@section('title', 'Add asset to GLPI')

@section('content')

    <a class="back-link" href="{{ route('manual.create', ['q' => $serial]) }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to search
    </a>

    <div class="page-head">
        <h1 class="page-title">Add asset to GLPI</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Create an asset that's missing from GLPI. It will be flagged as
        <strong>additional</strong> in the GLPI comment so it can be reviewed later.
    </p>

    @if (session('error'))
        <div class="alert alert-warn">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-warn">
            <strong>Please fix:</strong>
            <ul style="margin:6px 0 0 18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('glpi.add.store') }}">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-field">
                        <label>Type <span class="req">*</span></label>
                        <select class="select" name="itemtype" style="min-width:0;">
                            @foreach ($types as $t)
                                <option value="{{ $t }}" @selected(old('itemtype', 'Computer') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Name / Hostname <span class="req">*</span></label>
                        <input class="input" name="name" value="{{ old('name') }}" placeholder="e.g. DLKK-NB-SALES2" />
                    </div>
                    <div class="form-field">
                        <label>Serial number</label>
                        <input class="input" name="serial" value="{{ old('serial', $serial) }}" placeholder="Serial" />
                    </div>
                    <div class="form-field">
                        <label>Asset tag (inventory no.)</label>
                        <input class="input" name="otherserial" value="{{ old('otherserial') }}" placeholder="e.g. PC-00123" />
                    </div>
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label>Comment</label>
                    <textarea class="textarea" name="comment" rows="3" placeholder="Optional note (an “additional” marker is added automatically).">{{ old('comment') }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="submit" class="btn btn-primary">Create in GLPI</button>
            <a class="btn btn-ghost" href="{{ route('manual.create', ['q' => $serial]) }}">Cancel</a>
        </div>
    </form>

@endsection
