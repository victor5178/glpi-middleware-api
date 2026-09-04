@php
    $isNew = empty($role);
    $perms = $role['permissions'] ?? [];
    $rid   = $role['id'] ?? null;
@endphp

<div class="card" style="margin-bottom:14px;">
    <div class="card-body">
        <form method="post" action="{{ route('access.role.save') }}">
            @csrf
            @if (! $isNew)<input type="hidden" name="id" value="{{ $rid }}">@endif

            <div class="form-grid" style="align-items:end;">
                <div class="form-field">
                    <label>{{ $isNew ? 'New role name' : 'Role name' }} <span class="req">*</span></label>
                    <input class="input" name="name" value="{{ $role['name'] ?? '' }}"
                           placeholder="e.g. Forms Operator" required>
                </div>
                <div class="form-field">
                    <label>Full administrator?</label>
                    <label class="check" style="margin-top:8px;">
                        <input type="checkbox" name="is_admin" value="1" @checked(! empty($role['is_admin']))>
                        Full access + manage User Access
                    </label>
                </div>
            </div>

            <div class="section-label">Permissions <span style="color:var(--muted);font-weight:400;font-size:.8rem;">(ignored for administrators)</span></div>
            <div style="overflow-x:auto;">
                <table class="perm-grid">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Module</th>
                            @foreach ($actions as $action)<th>{{ ucfirst($action) }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $key => $label)
                            <tr>
                                <td style="text-align:left;">{{ $label }}</td>
                                @foreach ($actions as $action)
                                    <td>
                                        <input type="checkbox" name="permissions[{{ $key }}][{{ $action }}]" value="1"
                                            @checked(! empty($perms[$key][$action]))>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:14px;">
                <button type="submit" class="btn btn-primary">{{ $isNew ? 'Create role' : 'Save changes' }}</button>
            </div>
        </form>

        @if (! $isNew)
            <form method="post" action="{{ route('access.role.delete') }}"
                  onsubmit="return confirm('Delete the “{{ $role['name'] }}” role? Users on it fall back to the default role.');"
                  style="margin:10px 0 0;">
                @csrf @method('DELETE')
                <input type="hidden" name="id" value="{{ $rid }}">
                <button type="submit" class="btn btn-ghost" style="color:var(--danger,#b91c1c);">Delete role</button>
            </form>
        @endif
    </div>
</div>
