@extends('layouts.app')

@section('title', 'Form '.($form['reference_no'] ?? ('#'.$form['id'])))

@php
    $statusClass = fn ($s) => match ($s) {
        'Approved'  => 'status-approved',
        'Completed' => 'status-completed',
        default     => 'status-pending',
    };
    $fmt = fn ($d, $f = 'd M Y') => $d ? \Illuminate\Support\Carbon::parse($d)->format($f) : '—';
    $isForm10 = \Illuminate\Support\Str::contains((string) ($form['form_type'] ?? ''), 'Form 10');
@endphp

@section('content')

    <a class="back-link" href="{{ route('forms.index') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to forms
    </a>

    <div class="page-head">
        <h1 class="page-title">
            {{ $form['reference_no'] ?: ($form['form_type'] ?: ('Form #'.$form['id'])) }}
            <span class="status-badge {{ $statusClass($form['status']) }}" style="margin-left:8px;">{{ $form['status'] }}</span>
        </h1>
    </div>

    @if (session('success'))<div class="alert alert-muted">{{ session('success') }}</div>@endif
    @if (session('warning'))<div class="alert alert-warn">⚠ {{ session('warning') }}</div>@endif
    @if (session('error'))<div class="alert alert-warn">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-warn"><ul style="margin:0 0 0 18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @php $fwdActive = \App\Http\Controllers\FormsController::forwardingActive($form); @endphp
    @if ($fwdActive)
        <div class="alert fwd-banner">
            ↪ <strong>Email forwarding active</strong>
            @if (!empty($form['forward_to'])) to <strong>{{ $form['forward_to'] }}</strong>@endif
            @if (!empty($form['forward_until'])) until <strong>{{ \Illuminate\Support\Carbon::parse($form['forward_until'])->format('d M Y') }}</strong>@else (no end date)@endif.
            When forwarding is turned off, tick <em>“Forwarding disabled”</em> below to clear this.
        </div>
    @endif

    {{-- Scans --}}
    <div class="card">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">Scanned form</div>
            @if (! empty($form['images']))
                <div class="edit-photos">
                    @foreach ($form['images'] as $img)
                        @php
                            $src = url('media/'.ltrim($img['img_dir'], '/'));
                            $isPdf = \Illuminate\Support\Str::endsWith(strtolower($img['img_dir']), '.pdf');
                        @endphp
                        @if ($isPdf)
                            <a class="pdf-tile" href="{{ $src }}" target="_blank" rel="noopener">
                                <span class="pdf-ico">📄</span>
                                <span>Open PDF</span>
                            </a>
                        @else
                            <span class="zoom-wrap">
                                <img src="{{ $src }}" alt="Form scan" loading="lazy">
                            </span>
                        @endif
                    @endforeach
                </div>
            @else
                <p style="color:var(--muted);margin:0;">No scan attached.</p>
            @endif
        </div>
    </div>

    {{-- Details + status (editable with perm; read-only otherwise) --}}
    @perm('forms','edit')
        <form method="post" action="{{ route('forms.update', ['id' => $form['id']]) }}" class="card" style="margin-top:16px;">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="section-label" style="margin-top:0;">Details &amp; status</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label>Form type</label>
                        <input class="input" name="form_type" value="{{ old('form_type', $form['form_type']) }}">
                    </div>
                    <div class="form-field">
                        <label>Reference no.</label>
                        <input class="input" name="reference_no" value="{{ old('reference_no', $form['reference_no']) }}">
                    </div>
                    <div class="form-field">
                        <label>Date received</label>
                        <input class="input" type="date" name="received_date" value="{{ old('received_date', $form['received_date'] ? \Illuminate\Support\Carbon::parse($form['received_date'])->format('Y-m-d') : '') }}">
                    </div>
                    <div class="form-field">
                        <label>From (sender / dept)</label>
                        <input class="input" name="from_party" value="{{ old('from_party', $form['from_party']) }}">
                    </div>
                    <div class="form-field">
                        <label>Company</label>
                        <input class="input" name="company" value="{{ old('company', $form['company'] ?? '') }}" list="companyList">
                        <datalist id="companyList">
                            @foreach (config('forms.companies', []) as $c)<option value="{{ $c }}">@endforeach
                        </datalist>
                    </div>
                    <div class="form-field">
                        <label>Status</label>
                        <select class="select" name="status" style="min-width:0;">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected($form['status'] === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Status note (optional)</label>
                        <input class="input" name="note" placeholder="Reason / reference for this change">
                    </div>
                </div>
                <div class="form-field" style="margin-top:14px;">
                    <label>Remarks</label>
                    <textarea class="textarea" name="remarks" rows="3">{{ old('remarks', $form['remarks']) }}</textarea>
                </div>

                {{-- Email forwarding (Form 10 only) --}}
                @if ($isForm10)
                <div class="section-label">Email forwarding</div>
                <label class="check" style="margin-bottom:10px;">
                    <input type="checkbox" name="email_forwarding" value="1" @checked(old('email_forwarding', $form['email_forwarding'] ?? 0))>
                    Email is temporarily forwarded
                </label>
                <div class="form-grid">
                    <div class="form-field">
                        <label>Forward to</label>
                        <input class="input" name="forward_to" value="{{ old('forward_to', $form['forward_to'] ?? '') }}" placeholder="user / address">
                    </div>
                    <div class="form-field">
                        <label>Forward until</label>
                        <input class="input" type="date" name="forward_until" value="{{ old('forward_until', ($form['forward_until'] ?? null) ? \Illuminate\Support\Carbon::parse($form['forward_until'])->format('Y-m-d') : '') }}">
                    </div>
                </div>
                <label class="check" style="margin-top:10px;">
                    <input type="checkbox" name="forwarding_done" value="1" @checked(old('forwarding_done', $form['forwarding_done'] ?? 0))>
                    Forwarding disabled (clears the indicator)
                </label>
                @endif

                <div style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </form>
    @endperm

    @perm('forms','view')
        @php $canEdit = app(\App\Services\Rbac::class)->can('forms', 'edit'); @endphp
        @unless ($canEdit)
            <div class="card" style="margin-top:16px;">
                <div class="card-body">
                    <div class="section-label" style="margin-top:0;">Details</div>
                    <table class="kv">
                        <tr><th>Form type</th><td>{{ $form['form_type'] ?: '—' }}</td></tr>
                        <tr><th>Reference no.</th><td>{{ $form['reference_no'] ?: '—' }}</td></tr>
                        <tr><th>Date received</th><td>{{ $fmt($form['received_date']) }}</td></tr>
                        <tr><th>From</th><td>{{ $form['from_party'] ?: '—' }}</td></tr>
                        <tr><th>Company</th><td>{{ $form['company'] ?? '' ?: '—' }}</td></tr>
                        <tr><th>Status</th><td>{{ $form['status'] }}</td></tr>
                        @if ($isForm10 && !empty($form['email_forwarding']))
                            <tr><th>Email forwarding</th><td>
                                {{ !empty($form['forwarding_done']) ? 'Disabled' : 'Active' }}
                                @if (!empty($form['forward_to'])) → {{ $form['forward_to'] }}@endif
                                @if (!empty($form['forward_until'])) until {{ $fmt($form['forward_until']) }}@endif
                            </td></tr>
                        @endif
                        <tr><th>Remarks</th><td style="white-space:pre-wrap;">{{ $form['remarks'] ?: '—' }}</td></tr>
                    </table>
                </div>
            </div>
        @endunless
    @endperm

    {{-- OCR text --}}
    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">Extracted text (OCR)</div>
            @if (! empty($form['ocr_text']))
                <details>
                    <summary style="cursor:pointer;color:var(--brand,#2563eb);">Show / hide OCR text</summary>
                    <pre class="ocr-text">{{ $form['ocr_text'] }}</pre>
                </details>
            @else
                <p style="color:var(--muted);margin:0;">No text was extracted (OCR disabled, or the scan couldn’t be read).</p>
            @endif
            @if (! empty($form['ocr_source']))
                <p style="color:var(--muted);font-size:.78rem;margin:10px 0 0;">Source: {{ str_replace('_', ' ', $form['ocr_source']) }}</p>
            @endif
        </div>
    </div>

    {{-- Signatures --}}
    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">
                Signatures
                @if (! empty($form['has_signature']))<span class="sig-chip sig-yes">✓ signed</span>
                @else<span class="sig-chip sig-no">no signature detected</span>@endif
            </div>
            @if (! empty($form['signatures']))
                <div class="sig-list">
                    @foreach ($form['signatures'] as $sig)
                        <div class="sig-row">
                            <span class="sig-dot {{ $sig['detected'] ? 'ok' : ($sig['anchor_found'] ? 'miss' : 'na') }}"></span>
                            <span class="sig-label">{{ $sig['signer_label'] }}</span>
                            <span class="muted">
                                @if ($sig['detected']) detected
                                @elseif ($sig['anchor_found']) anchor found, no ink
                                @else label not found @endif
                                @if (isset($sig['ink_density']) && $sig['ink_density'] !== null)
                                    · ink {{ round($sig['ink_density'] * 100, 1) }}%
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
                <p style="color:var(--muted);font-size:.78rem;margin:10px 0 0;">Heuristic detection (ink near the label) — confirms something was written, not who signed.</p>
            @else
                <p style="color:var(--muted);margin:0;">No signature analysis for this form (no template signers, or OCR/signature deps not enabled).</p>
            @endif
            @perm('forms','edit')
                <form method="post" action="{{ route('forms.reprocess', ['id' => $form['id']]) }}" style="margin-top:12px;">
                    @csrf
                    <button type="submit" class="btn btn-ghost">↻ Re-run OCR &amp; signature detection</button>
                </form>
            @endperm
        </div>
    </div>

    {{-- History --}}
    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">Status history</div>
            @if (! empty($history))
                <ul class="timeline">
                    @foreach ($history as $h)
                        <li>
                            <span class="status-badge {{ $statusClass($h['to_status']) }}">{{ $h['to_status'] }}</span>
                            @if (!empty($h['from_status']))<span class="muted">from {{ $h['from_status'] }}</span>@endif
                            <span class="muted">· {{ $fmt($h['created_at'], 'd M Y H:i') }}</span>
                            @if (!empty($h['actor']))<span class="muted">· {{ $h['actor'] }}</span>@endif
                            @if (!empty($h['note']))<div style="margin-top:2px;">{{ $h['note'] }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p style="color:var(--muted);margin:0;">No history recorded.</p>
            @endif
        </div>
    </div>

    {{-- Notes (IT can append; each note is timestamped) --}}
    <div class="card" style="margin-top:16px;" id="notes">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">Notes</div>

            @perm('forms','edit')
                <form method="post" action="{{ route('forms.note', ['id' => $form['id']]) }}" style="margin-bottom:14px;">
                    @csrf
                    <textarea class="textarea" name="note" rows="3" placeholder="Add a note…" required>{{ old('note') }}</textarea>
                    <div style="margin-top:8px;">
                        <button type="submit" class="btn btn-primary">Add note</button>
                    </div>
                </form>
            @endperm

            @if (! empty($form['notes']))
                <ul class="note-list">
                    @foreach ($form['notes'] as $n)
                        <li class="note-item">
                            <div class="note-body">{{ $n['note'] }}</div>
                            <div class="note-meta">
                                {{ $n['author'] ?: 'Unknown' }}
                                · {{ $fmt($n['created_at'], 'd M Y H:i') }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p style="color:var(--muted);margin:0;">No notes yet.</p>
            @endif
        </div>
    </div>

    {{-- Delete --}}
    @perm('forms','delete')
        <form method="post" action="{{ route('forms.destroy', ['id' => $form['id']]) }}"
              onsubmit="return confirm('Delete this form? A snapshot is archived in the Audit Trail.');"
              style="margin-top:22px;padding-top:16px;border-top:1px solid var(--line);">
            @csrf @method('DELETE')
            <button type="submit" class="btn" style="background:var(--danger,#b91c1c);color:#fff;">Delete form</button>
            <span style="color:var(--muted);font-size:.82rem;margin-left:8px;">Removes this form; a full snapshot is kept in the Audit Trail.</span>
        </form>
    @endperm

@endsection
