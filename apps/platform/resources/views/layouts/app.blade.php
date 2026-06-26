<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OmniBridge' }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: #f7f7f8; color: #1f2933; }
        header { background: #fff; border-bottom: 1px solid #d9dee5; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        main { max-width: 1120px; margin: 0 auto; padding: 24px; }
        h1, h2, h3 { margin: 0 0 12px; }
        p { line-height: 1.5; }
        a { color: #0b5cad; text-decoration: none; }
        a:hover { text-decoration: underline; }
        nav { display: flex; align-items: center; gap: 14px; }
        table { border-collapse: collapse; width: 100%; background: #fff; border: 1px solid #d9dee5; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e7ebf0; text-align: left; vertical-align: top; }
        th { background: #f0f3f7; font-size: 13px; }
        label { display: block; font-weight: 650; margin: 12px 0 6px; }
        input, select { box-sizing: border-box; width: 100%; max-width: 560px; padding: 9px 10px; border: 1px solid #b8c2cc; border-radius: 6px; background: #fff; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 7px 12px; border: 1px solid #0b5cad; border-radius: 6px; background: #0b5cad; color: #fff; font-weight: 650; cursor: pointer; text-decoration: none; }
        button.secondary, .button.secondary { background: #fff; color: #0b5cad; }
        .panel { background: #fff; border: 1px solid #d9dee5; border-radius: 8px; padding: 18px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
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
    <div>
        <strong>OmniBridge</strong>
        <span class="muted">WooCommerce ↔ Front Systems</span>
    </div>
    <nav>
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('organizations.index') }}">Organizations</a>
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
