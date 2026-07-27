<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GLPI Audit Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f8; }
        .navbar-brand { font-weight: 700; letter-spacing: .3px; }
        .stat-card .value { font-size: 2rem; font-weight: 700; line-height: 1; }
        .stat-card .label { color: #6c757d; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; }
        .thumb {
            width: 100%; height: 170px; object-fit: cover; background: #e9ecef;
            border-top-left-radius: .5rem; border-top-right-radius: .5rem;
        }
        .thumb-placeholder {
            width: 100%; height: 170px; display: flex; align-items: center; justify-content: center;
            background: #e9ecef; color: #adb5bd; font-size: .9rem;
            border-top-left-radius: .5rem; border-top-right-radius: .5rem;
        }
        .detail-photo { width: 100%; max-height: 460px; object-fit: contain; background: #000; border-radius: .5rem; }
        .card-asset { transition: box-shadow .15s ease; }
        .card-asset:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.1); }
        a.card-link { text-decoration: none; color: inherit; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">🖥️ GLPI Audit Dashboard</a>
            <span class="navbar-text text-white-50 small">{{ config('app.name') }}</span>
        </div>
    </nav>

    <main class="container py-4">
        @yield('content')
    </main>

    <footer class="container text-center text-muted small py-4">
        Reads from middleware · data is read-only
    </footer>
</body>
</html>
