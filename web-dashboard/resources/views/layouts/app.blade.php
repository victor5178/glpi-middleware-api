<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ITD Dashboard')</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    {{-- Pure-CSS toggle for the left navigation drawer --}}
    <input type="checkbox" id="navToggle" class="nav-toggle-cb" hidden>

    <header class="topbar">
        <div class="container">
            <label for="navToggle" class="hamburger" role="button" tabindex="0" aria-label="Toggle menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </label>
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="logo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                    </svg>
                </span>
                ITD Dashboard
            </a>
            <nav class="top-actions">
                <a class="nav-link" href="{{ route('scan') }}">Scan</a>
                <a class="nav-link primary" href="{{ route('manual.create') }}">+ Manual entry</a>
            </nav>
            @if (session('glpi_user'))
                <span class="nav-user topbar-user">{{ session('glpi_user') }}</span>
            @endif
        </div>
    </header>

    {{-- Left navigation drawer (expand / hide via the hamburger) --}}
    <label for="navToggle" class="nav-overlay"></label>
    <aside class="sidebar">
        <div class="sidebar-head">
            <span>Menu</span>
            <label for="navToggle" class="sidebar-close" role="button" tabindex="0" aria-label="Close menu">✕</label>
        </div>
        <nav class="sidebar-nav">
            <a class="side-link @class(['active' => request()->routeIs('dashboard', 'scanned.*')])" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="side-link @class(['active' => request()->routeIs('asset-review')])" href="{{ route('asset-review') }}">Asset Review</a>
            <a class="side-link @class(['active' => request()->routeIs('discrepancy')])" href="{{ route('discrepancy') }}">Discrepancy Review</a>
            <a class="side-link @class(['active' => request()->routeIs('audit-trail')])" href="{{ route('audit-trail') }}">Audit Trail</a>
            <a class="side-link @class(['active' => request()->routeIs('report')])" href="{{ route('report') }}">Report</a>
            <a class="side-link @class(['active' => request()->routeIs('scan')])" href="{{ route('scan') }}">Scan</a>
            <a class="side-link primary @class(['active' => request()->routeIs('manual.*')])" href="{{ route('manual.create') }}">+ Manual entry</a>
        </nav>
        @if (session('glpi_user'))
            <div class="sidebar-foot">
                <span class="nav-user">{{ session('glpi_user') }}</span>
                <form method="post" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="side-link">Logout</button>
                </form>
            </div>
        @endif
    </aside>

    <main class="container page">
        @yield('content')
    </main>

    <footer class="footer container">
        Reads from the GLPI middleware · data is read-only
    </footer>
</body>
</html>
