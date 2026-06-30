<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OmniBridge' }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: radial-gradient(circle at top left, #eef7ff 0, #f7f7f8 34%, #f6f9f6 100%); color: #1f2933; }
        header { background: rgba(255, 255, 255, .9); border-bottom: 1px solid #d9dee5; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: sticky; top: 0; z-index: 2; backdrop-filter: blur(12px); }
        main { max-width: 1180px; margin: 0 auto; padding: 24px; }
        h1, h2, h3 { margin: 0 0 12px; }
        p { line-height: 1.5; }
        a { color: #0b5cad; text-decoration: none; }
        a:hover { text-decoration: underline; }
        nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        nav a { color: #334155; font-weight: 650; padding: 7px 9px; border-radius: 999px; }
        nav a:hover { background: #eef4fb; text-decoration: none; }
        .brand { display: flex; flex-direction: column; gap: 2px; }
        .nav-primary { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        table { border-collapse: collapse; width: 100%; background: #fff; border: 1px solid #d9dee5; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e7ebf0; text-align: left; vertical-align: top; }
        th { background: #f0f3f7; font-size: 13px; }
        label { display: block; font-weight: 650; margin: 12px 0 6px; }
        input, select { box-sizing: border-box; width: 100%; max-width: 560px; padding: 9px 10px; border: 1px solid #b8c2cc; border-radius: 6px; background: #fff; }
        input[type="checkbox"] { width: auto; max-width: none; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 7px 12px; border: 1px solid #0b5cad; border-radius: 6px; background: #0b5cad; color: #fff; font-weight: 650; cursor: pointer; text-decoration: none; }
        button.secondary, .button.secondary { background: #fff; color: #0b5cad; }
        .panel { background: rgba(255, 255, 255, .94); border: 1px solid #d9dee5; border-radius: 8px; padding: 18px; margin-bottom: 18px; box-shadow: 0 14px 40px rgba(15, 23, 42, .04); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .metric { border: 1px solid #d9dee5; border-radius: 8px; padding: 14px; background: linear-gradient(180deg, #fff, #f8fbff); }
        .metric strong { display: block; font-size: 28px; line-height: 1.1; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 9px; font-size: 12px; font-weight: 750; background: #e8f2ff; color: #0b5cad; }
        .badge.ready { background: #e8f7ee; color: #17633a; }
        .badge.warning-badge { background: #fff3c4; color: #684a00; }
        .badge.blocked { background: #ffe8e8; color: #8a2525; }
        .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #9aa5b1; margin-right: 6px; }
        .status-dot.ready { background: #23a36f; }
        .status-dot.warning { background: #d99700; }
        .status-dot.blocked { background: #d14b4b; }
        .action-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .progress { height: 10px; background: #e7ebf0; border-radius: 999px; overflow: hidden; }
        .progress > span { display: block; height: 100%; background: linear-gradient(90deg, #0b5cad, #23a36f); }
        .muted { color: #5d6978; font-size: 14px; }
        .notice { border: 1px solid #b7d7b7; background: #edf8ed; color: #234c23; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .warning { border: 1px solid #e0c36e; background: #fff8dc; color: #624a00; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .danger { border: 1px solid #e19a9a; background: #fff0f0; color: #7a2424; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .inline-form { display: inline; }
        .secret-hint { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <strong>OmniBridge</strong>
        <span class="muted">WooCommerce ↔ Front Systems</span>
    </div>
    <nav>
        @auth
            <div class="nav-primary">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('connections.index') }}">Connections</a>
                <a href="{{ route('product-sync.index') }}">Product Sync</a>
                <a href="{{ route('advanced.index') }}">Advanced</a>
            </div>
            <form class="inline-form" method="post" action="{{ route('logout') }}">
                @csrf
                <button class="secondary" type="submit">Log out</button>
            </form>
        @endauth
    </nav>
</header>
<main>
    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="danger">
            <strong>Please check the form.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{ $slot ?? '' }}
    @yield('content')
</main>
</body>
</html>
