@extends('layouts.app')

@section('title', 'Scan / upload form')

@section('content')

    <a class="back-link" href="{{ route('forms.index') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to forms
    </a>

    <div class="page-head">
        <h1 class="page-title">Scan / upload a form</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Attach a photo or scan of the form. It’s logged as <strong>Pending Approval</strong> and the text is
        read automatically (OCR) so you can search it later. You can correct any field here or after saving.
    </p>

    @if (session('error'))<div class="alert alert-warn">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-warn">
            <strong>Please fix:</strong>
            <ul style="margin:6px 0 0 18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('forms.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Form details</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label>Form type</label>
                        <select class="select" name="form_type" id="formType" style="min-width:0;">
                            <option value="">— Select —</option>
                            @foreach (config('forms.templates', []) as $t)
                                <option value="{{ $t }}" @selected(old('form_type') === $t)>{{ $t }}</option>
                            @endforeach
                            <option value="Other" @selected(old('form_type') === 'Other')>Other…</option>
                        </select>
                        <input class="input" name="form_type_other" id="formTypeOther" value="{{ old('form_type_other') }}"
                               placeholder="Type the form type" style="margin-top:8px;display:none;">
                    </div>
                    <div class="form-field">
                        <label>Reference no.</label>
                        <input class="input" name="reference_no" value="{{ old('reference_no') }}" placeholder="e.g. PR-2026-0042">
                    </div>
                    <div class="form-field">
                        <label>Date received</label>
                        <input class="input" type="date" name="received_date" value="{{ old('received_date', now()->format('Y-m-d')) }}">
                    </div>
                    <div class="form-field">
                        <label>From (sender / dept)</label>
                        <input class="input" name="from_party" value="{{ old('from_party') }}" placeholder="e.g. Finance Dept">
                    </div>
                    <div class="form-field">
                        <label>Company</label>
                        <input class="input" name="company" value="{{ old('company') }}" list="companyList" placeholder="e.g. DAI LIENG MACHINERY SDN BHD">
                        <datalist id="companyList">
                            @foreach (config('forms.companies', []) as $c)<option value="{{ $c }}">@endforeach
                        </datalist>
                    </div>
                </div>
                <div class="form-field" style="margin-top:14px;">
                    <label>Remarks</label>
                    <textarea class="textarea" name="remarks" rows="3" placeholder="Optional notes…">{{ old('remarks') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Scan / photos <span class="req">*</span></div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <label class="btn btn-primary" style="cursor:pointer;">
                        📷 Take photo
                        <input type="file" id="photoCapture" accept="image/*" capture="environment" hidden>
                    </label>
                    <label class="btn btn-ghost" style="cursor:pointer;">
                        Choose files
                        <input type="file" name="images[]" id="photoInput" accept="image/*,application/pdf" multiple hidden>
                    </label>
                </div>
                <p style="color:var(--muted);font-size:.82rem;margin:8px 0 0;">Images or PDF files are accepted. Photos are compressed automatically; add multiple pages if the form has them. (Text extraction (OCR) reads images — for a PDF, upload it plus a photo of the page if you want the text searchable.)</p>
                <div id="photoPreview" class="edit-photos" style="margin-top:12px;"></div>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="submit" class="btn btn-primary">Save form</button>
            <a class="btn btn-ghost" href="{{ route('forms.index') }}">Cancel</a>
        </div>
    </form>

    <script src="/js/photo-input.js"></script>
    <script>
        PhotoInput.enhance({
            input: document.getElementById('photoInput'),
            captureInput: document.getElementById('photoCapture'),
            preview: document.getElementById('photoPreview'),
            maxEdge: 2200, quality: 0.72
        });
        // Reveal the free-text box only when "Other" is chosen as the form type.
        (function () {
            var sel = document.getElementById('formType'), other = document.getElementById('formTypeOther');
            if (!sel || !other) return;
            function sync() { other.style.display = (sel.value === 'Other') ? '' : 'none'; }
            sel.addEventListener('change', sync); sync();
        })();
    </script>

@endsection
