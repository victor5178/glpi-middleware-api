<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · GLPI Audit Dashboard</title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
        .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { width: 100%; max-width: 380px; }
        .login-head { text-align: center; margin-bottom: 18px; }
        .login-logo {
            width: 52px; height: 52px; border-radius: 13px; margin: 0 auto 12px;
            background: linear-gradient(90deg, var(--brand-dark), var(--brand));
            display: grid; place-items: center;
        }
        .login-title { font-size: 1.25rem; font-weight: 700; }
        .login-sub { color: var(--muted); font-size: .9rem; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-head">
                <div class="login-logo">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                    </svg>
                </div>
                <div class="login-title">GLPI Audit Dashboard</div>
                <div class="login-sub">Sign in with your GLPI account</div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-warn">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-warn">Enter your username and password.</div>
                    @endif

                    <form method="post" action="{{ route('login.attempt') }}">
                        @csrf
                        <div class="form-field">
                            <label for="username">GLPI username</label>
                            <input class="input" id="username" name="username" value="{{ old('username') }}"
                                   autocomplete="username" autofocus>
                        </div>
                        <div class="form-field">
                            <label for="password">Password</label>
                            <input class="input" id="password" name="password" type="password"
                                   autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:6px;">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
