<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Budget Tracker')</title>
    <style>
        :root { --bg:#f5f7fa; --text:#1a202c; --card:#fff; --border:#e2e8f0; --primary:#2b6cb0; --danger:#e53e3e; --success:#38a169; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
        header { background:#fff; border-bottom:1px solid var(--border); padding:1rem; }
        nav { max-width:960px; margin:0 auto; display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
        nav a { text-decoration:none; color:var(--text); padding:.25rem .5rem; border-radius:4px; }
        nav a.active, nav a:hover { background:var(--bg); }
        .container { max-width:960px; margin:0 auto; padding:1rem; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:1rem; margin-bottom:1rem; }
        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:.75rem; border-bottom:1px solid var(--border); text-align:left; }
        th { background:var(--bg); font-size:.875rem; text-transform:uppercase; }
        .btn { display:inline-block; padding:.5rem .75rem; border:1px solid var(--border); border-radius:4px; text-decoration:none; cursor:pointer; background:#fff; }
        .btn-primary { background:var(--primary); color:#fff; border-color:var(--primary); }
        .btn-danger { background:var(--danger); color:#fff; border-color:var(--danger); }
        .btn-sm { padding:.25rem .5rem; font-size:.875rem; }
        form { display:flex; flex-direction:column; gap:1rem; }
        .form-grid { display:grid; gap:1rem; grid-template-columns:1fr; }
        @media (min-width:640px){ .form-grid { grid-template-columns:repeat(2,1fr); } }
        label { font-size:.875rem; font-weight:600; display:block; margin-bottom:.25rem; }
        input, select, textarea { width:100%; padding:.5rem; border:1px solid var(--border); border-radius:4px; }
        .alert { padding:.75rem 1rem; border-radius:4px; margin-bottom:1rem; }
        .alert-success { background:#f0fff4; border:1px solid #c6f6d5; color:#22543d; }
        .flex { display:flex; }
        .gap { gap:.5rem; }
        .justify-between { justify-content:space-between; }
        .items-center { align-items:center; }
        .mb { margin-bottom:1rem; }
        .text-muted { color:#718096; }
        .grid { display:grid; gap:1rem; }
        @media (min-width:640px){ .grid-3 { grid-template-columns:repeat(3,1fr); } }
        @media (min-width:768px){ .grid-4 { grid-template-columns:repeat(4,1fr); } }
        .stat { padding:1rem; background:var(--bg); border-radius:8px; text-align:center; }
        .stat-value { font-size:1.5rem; font-weight:700; }
        .stat-label { font-size:.875rem; color:#718096; }
        .text-danger { color:var(--danger); }
        .text-success { color:var(--success); }
    </style>
</head>
<body>
    <header>
        <nav>
            <strong>Budget Tracker</strong>
            @auth
                <a href="{{ route('transactions.index') }}" class="{{ request()->routeIs('transactions.*') ? 'active' : '' }}">Movimientos</a>
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">Reportes</a>
                <form method="POST" action="{{ route('logout') }}" style="margin-left:auto;">
                    @csrf
                    <button type="submit" class="btn btn-sm">Logout</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}" style="margin-left:auto;">Login</a>
            @endguest
        </nav>
    </header>
    <main class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
