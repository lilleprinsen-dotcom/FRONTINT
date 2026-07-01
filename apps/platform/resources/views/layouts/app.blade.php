<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OmniBridge' }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: #f4f7fb; color: #1f2933; }
        header { background: rgba(255, 255, 255, .9); border-bottom: 1px solid #d9dee5; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: sticky; top: 0; z-index: 2; backdrop-filter: blur(12px); }
        main { max-width: 1180px; margin: 0 auto; padding: 24px; }
        h1, h2, h3 { margin: 0 0 12px; letter-spacing: 0; }
        h1 { font-size: clamp(28px, 4vw, 44px); line-height: 1.05; }
        h2 { font-size: 21px; }
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
        .panel { background: rgba(255, 255, 255, .96); border: 1px solid #d9dee5; border-radius: 8px; padding: 18px; margin-bottom: 18px; box-shadow: 0 14px 40px rgba(15, 23, 42, .04); }
        .page-header { padding: 24px; }
        .page-header p { max-width: 760px; font-size: 17px; }
        .kicker { display: inline-flex; margin-bottom: 10px; color: #486174; font-weight: 760; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .owner-flow { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .flow-step { border: 1px solid #d9dee5; border-radius: 8px; padding: 16px; background: #fff; }
        .flow-step strong { display: block; font-size: 18px; margin-bottom: 6px; }
        .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 999px; background: #e8f2ff; color: #0b5cad; font-weight: 800; margin-bottom: 10px; }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .metric { border: 1px solid #d9dee5; border-radius: 8px; padding: 14px; background: linear-gradient(180deg, #fff, #f8fbff); }
        .metric strong { display: block; font-size: 28px; line-height: 1.1; }
        .status-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .status-card { border: 1px solid #d9dee5; border-radius: 8px; padding: 16px; background: #fff; }
        .status-card strong { display: block; font-size: 24px; margin: 4px 0; }
        .status-card.ready { border-color: #9fd8b7; background: #f2fbf5; }
        .status-card.warning { border-color: #e7ca77; background: #fff9df; }
        .status-card.blocked { border-color: #e5a1a1; background: #fff3f3; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 9px; font-size: 12px; font-weight: 750; background: #e8f2ff; color: #0b5cad; }
        .badge.ready { background: #e8f7ee; color: #17633a; }
        .badge.warning-badge { background: #fff3c4; color: #684a00; }
        .badge.blocked { background: #ffe8e8; color: #8a2525; }
        .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #9aa5b1; margin-right: 6px; }
        .status-dot.ready { background: #23a36f; }
        .status-dot.warning { background: #d99700; }
        .status-dot.blocked { background: #d14b4b; }
        .action-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .split-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .two-column { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; align-items: start; }
        .table-wrap { overflow-x: auto; border-radius: 8px; }
        .summary-list { display: grid; gap: 10px; }
        .summary-item { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #e7ebf0; }
        .summary-item:last-child { border-bottom: 0; }
        .compact-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .compact-list li { display: flex; gap: 8px; align-items: flex-start; }
        details.technical-details { margin-top: 10px; color: #5d6978; font-size: 14px; }
        details.technical-details summary { cursor: pointer; font-weight: 650; }
        .progress { height: 10px; background: #e7ebf0; border-radius: 999px; overflow: hidden; }
        .progress > span { display: block; height: 100%; background: linear-gradient(90deg, #0b5cad, #23a36f); }
        .progress.large { height: 18px; }
        .progress-segments { display: flex; height: 18px; overflow: hidden; background: #e7ebf0; border-radius: 999px; }
        .progress-segments span { display: block; min-width: 0; }
        .progress-segments .ready-part { background: #23a36f; }
        .progress-segments .warning-part { background: #d99700; }
        .progress-segments .blocked-part { background: #d14b4b; }
        .next-step { border-left: 5px solid #0b5cad; background: #f7fbff; padding: 14px 16px; border-radius: 8px; }
        .simple-table th, .simple-table td { font-size: 14px; }
        .copy-box { width: 100%; max-width: none; min-height: 240px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; padding: 12px; border: 1px solid #b8c2cc; border-radius: 8px; box-sizing: border-box; background: #fbfdff; color: #1f2933; }
        .muted { color: #5d6978; font-size: 14px; }
        .notice { border: 1px solid #b7d7b7; background: #edf8ed; color: #234c23; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .warning { border: 1px solid #e0c36e; background: #fff8dc; color: #624a00; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .danger { border: 1px solid #e19a9a; background: #fff0f0; color: #7a2424; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .inline-form { display: inline; }
        .secret-hint { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        @media (max-width: 720px) {
            header { align-items: flex-start; flex-direction: column; }
            .two-column { grid-template-columns: 1fr; }
            main { padding: 16px; }
            .page-header { padding: 18px; }
        }
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
                <a href="{{ route('woo-readiness.index') }}">Woo Readiness</a>
                <a href="{{ route('product-sync.index') }}">Product Sync</a>
                <a href="{{ route('front-sales.index') }}">Front Sales</a>
                <a href="{{ route('testing-log.index') }}">Testing Log</a>
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
