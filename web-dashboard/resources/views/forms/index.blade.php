@extends('layouts.app')

@section('title', 'Forms Tracking')

@php
    $statusClass = fn ($s) => match ($s) {
        'Approved'  => 'status-approved',
        'Completed' => 'status-completed',
        default     => 'status-pending',
    };
    $firstImage = function ($form) {
        $imgs = array_filter(explode('||', (string) ($form['images'] ?? '')));
        return $imgs ? url('media/'.ltrim($imgs[0], '/')) : null;
    };
@endphp

@section('content')

    <div class="page-head">
        <h1 class="page-title">Forms Tracking <span class="muted">OCR &amp; approval status</span></h1>
        @perm('forms','execute')
            <a class="btn btn-primary" href="{{ route('forms.create') }}">+ Scan / upload form</a>
        @endperm
    </div>

    @if (session('success'))<div class="alert alert-muted">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-warn">{{ session('error') }}</div>@endif
    @if ($error)<div class="alert alert-warn">Middleware: {{ $error }}</div>@endif

    {{-- Status summary + filters --}}
    <div class="status-tabs">
        <a class="status-tab @class(['active' => $status === ''])" href="{{ route('forms.index', ['q' => $q]) }}">
            All <span class="count">{{ array_sum($counts) }}</span>
        </a>
        @foreach ($statuses as $s)
            <a class="status-tab {{ $statusClass($s) }} @class(['active' => $status === $s])"
               href="{{ route('forms.index', ['status' => $s, 'q' => $q]) }}">
                {{ $s }} <span class="count">{{ $counts[$s] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('forms.index') }}" class="filter" style="margin-bottom:16px;">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="field" style="flex:1;">
            <label for="q">Search</label>
            <input class="input" type="search" id="q" name="q" value="{{ $q }}"
                   placeholder="Reference no., type, sender, remarks, or OCR text…">
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:end;">Search</button>
        @if ($q)<a class="btn btn-ghost" style="align-self:end;" href="{{ route('forms.index', ['status' => $status]) }}">Clear</a>@endif
    </form>

    @if (empty($forms))
        <div class="alert alert-muted">
            No forms {{ $status ? 'with status “'.$status.'”' : 'yet' }}{{ $q ? ' matching “'.$q.'”' : '' }}.
            @perm('forms','execute')<a href="{{ route('forms.create') }}">Scan or upload one</a>.@endperm
        </div>
    @else
        <div class="grid">
            @foreach ($forms as $form)
                @php $thumb = $firstImage($form); @endphp
                <a class="asset" href="{{ route('forms.show', ['id' => $form['id']]) }}">
                    <div class="thumb-wrap">
                        @if ($thumb)
                            <img loading="lazy" src="{{ $thumb }}" alt="Form scan"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                            <div class="thumb-empty" style="display:none;">Scan unavailable</div>
                        @else
                            <div class="thumb-empty">No scan</div>
                        @endif
                    </div>
                    <div class="body">
                        <div class="row1">
                            <span class="name">{{ $form['reference_no'] ?: ($form['form_type'] ?: ('Form #'.$form['id'])) }}</span>
                            <span class="status-badge {{ $statusClass($form['status']) }}">{{ $form['status'] }}</span>
                        </div>
                        @if (!empty($form['form_type']))<span class="sub">{{ $form['form_type'] }}</span>@endif
                        @if (!empty($form['from_party']))<span class="sub">From: {{ $form['from_party'] }}</span>@endif
                        <span class="meta">
                            @if (!empty($form['received_date'])){{ \Illuminate\Support\Carbon::parse($form['received_date'])->format('d M Y') }} · @endif
                            {{ $form['created_by'] ?: 'Unknown' }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

@endsection
