<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @stack('head')
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f5f7;
            --card: #fff;
            --ink: #1c1e21;
            --muted: #5c6570;
            --line: #d9dee5;
            --accent: #1f5fbf;
            --ok: #0f6b4c;
            --warn: #9a4d00;
            --bad: #b3261e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--ink);
        }

        nav {
            background: var(--card);
            border-bottom: 1px solid var(--line);
        }

        nav .inner {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            gap: 4px;
            align-items: center;
        }

        nav strong { margin-right: 16px; }

        nav a {
            color: var(--muted);
            text-decoration: none;
            padding: 14px 12px;
            border-bottom: 2px solid transparent;
        }

        nav a.active {
            color: var(--ink);
            border-bottom-color: var(--accent);
        }

        main {
            max-width: 1080px;
            margin: 0 auto;
            padding: 28px 20px 64px;
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        h1 { margin: 0 0 4px; font-size: 26px; font-weight: 650; }
        h2 { margin: 0 0 12px; font-size: 17px; font-weight: 650; }
        p { margin: 0; line-height: 1.5; }
        .muted { color: var(--muted); font-size: 14px; }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 18px;
        }

        .table-wrap {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow-x: auto;
        }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); background: #f9fafb; }
        tr:last-child td { border-bottom: 0; }
        td.empty { text-align: center; color: var(--muted); padding: 28px; white-space: normal; }

        button, .button {
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            border-radius: 6px;
            padding: 7px 12px;
            font: inherit;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        button.danger { color: var(--bad); }

        input[type=text], input[type=date], input[type=datetime-local], select {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 7px 10px;
            font: inherit;
            font-size: 14px;
        }

        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-row label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--muted); }
        .form-row label.check { flex-direction: row; align-items: center; gap: 6px; padding-bottom: 8px; }
        .actions { display: flex; gap: 6px; }
        .actions form { margin: 0; }

        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            border-radius: 999px;
            padding: 2px 9px;
            background: #eef0f3;
            color: var(--muted);
        }

        .badge.ok { background: #e3f3ec; color: var(--ok); }
        .badge.warn { background: #fdf0e1; color: var(--warn); }
        .badge.bad { background: #fbe7e6; color: var(--bad); }

        .flash { border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 14px; }
        .flash.ok { background: #e3f3ec; color: var(--ok); }
        .flash.bad { background: #fbe7e6; color: var(--bad); }
        .flash ul { margin: 0; padding-left: 18px; }

        code { font-family: Consolas, "Courier New", monospace; font-size: 13px; }
    </style>
</head>
<body>
<nav>
    <div class="inner">
        <strong>{{ config('app.name') }}</strong>
        <a href="{{ route('punches') }}" @class(['active' => request()->routeIs('punches')])>Punch log</a>
        <a href="{{ route('students.index') }}" @class(['active' => request()->routeIs('students.*')])>Students</a>
    </div>
</nav>
<main>
    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="flash bad">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="flash bad">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
