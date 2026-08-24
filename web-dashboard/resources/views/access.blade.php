@extends('layouts.app')

@section('title', 'User Access')

@section('content')

    <div class="page-head">
        <h1 class="page-title">User Access <span class="muted">roles &amp; permissions</span></h1>
    </div>

    @if (session('status'))<div class="alert alert-muted">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-warn">{{ session('error') }}</div>@endif
    @if ($error)<div class="alert alert-warn">Middleware: {{ $error }}</div>@endif

    {{-- ============================ ASSIGN USERS ============================ --}}
    <div class="card">
        <div class="card-body">
            <div class="section-label" style="margin-top:0;">Assign a user to a role</div>
            <p style="color:var(--muted);font-size:.85rem;margin:0 0 12px;">
                Enter the person’s <strong>GLPI username</strong> (the same one they log in with) and pick a role.
            </p>
            <form method="post" action="{{ route('access.assign') }}" class="form-grid" style="align-items:end;">
                @csrf
                <div class="form-field">
                    <label>GLPI username <span class="req">*</span></label>
                    <input class="input" name="username" placeholder="e.g. victor.t" required>
                </div>
                <div class="form-field">
                    <label>Role <span class="req">*</span></label>
                    <select class="select" name="role_id" required style="min-width:0;">
                        @foreach ($roles as $r)
                            <option value="{{ $r['id'] }}">{{ $r['name'] }}@if(!empty($r['is_admin'])) (admin)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>

            @if (! empty($assignments))
                <table class="kv" style="margin-top:16px;width:100%;">
                    <tr><th style="text-align:left;">Username</th><th style="text-align:left;">Role</th><th></th></tr>
                    @foreach ($assignments as $a)
                        <tr>
                            <td>{{ $a['username'] }}</td>
                            <td>{{ $a['role_name'] }}@if(!empty($a['is_admin'])) <span class="pill pill-muted">admin</span>@endif</td>
                            <td style="text-align:right;">
                                <form method="post" action="{{ route('access.remove') }}" style="margin:0;"
                                      onsubmit="return confirm('Remove this assignment? The user falls back to the default role.');">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="username" value="{{ $a['username'] }}">
                                    <button class="btn btn-ghost" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p style="color:var(--muted);margin-top:14px;">No explicit assignments yet — everyone uses the default role
                    (<strong>{{ config('rbac.default_role') ?: 'none' }}</strong>).</p>
            @endif
        </div>
    </div>

    {{-- ============================ ROLES ============================ --}}
    <div class="page-head" style="margin-top:22px;">
        <h2 class="page-title" style="font-size:1.15rem;">Roles</h2>
    </div>
    <p style="color:var(--muted);font-size:.85rem;margin:0 0 12px;">
        Tick which modules a role can access and what it may do. An <strong>Administrator</strong> role
        (below) always has full access and can manage this page — its checkboxes are ignored.
    </p>

    @foreach ($roles as $role)
        @include('partials.access-role', ['role' => $role, 'modules' => $modules, 'actions' => $actions])
    @endforeach

    {{-- New role --}}
    @include('partials.access-role', ['role' => null, 'modules' => $modules, 'actions' => $actions])

@endsection
