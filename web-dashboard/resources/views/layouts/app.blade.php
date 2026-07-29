<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GLPI Audit Dashboard')</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header class="topbar">
        <div class="container">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="logo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                    </svg>
                </span>
                GLPI Audit Dashboard
            </a>
            <nav class="nav-links">
                <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="nav-link primary" href="{{ route('manual.create') }}">+ Manual entry</a>
            </nav>
        </div>
    </header>

    <main class="container page">
        @yield('content')
    </main>

    <footer class="footer container">
        Reads from the GLPI middleware · data is read-only
    </footer>
</body>
</html>
