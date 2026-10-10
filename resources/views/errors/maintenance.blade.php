<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        :root { --primary: #0D6EFD; --primary-light: #EFF6FF; --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
            background: #f8fafc; color: var(--text); font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { width: 100%; max-width: 460px; background: #fff; border: 1px solid var(--border); border-radius: 12px;
            padding: 36px 28px; text-align: center; }
        .icon { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; background: var(--primary-light);
            color: var(--primary); display: flex; align-items: center; justify-content: center; }
        h1 { font-size: 1.3rem; margin: 0 0 10px; }
        p { margin: 0; color: var(--muted); line-height: 1.55; white-space: pre-line; }
        .until { margin-top: 18px; display: inline-block; font-size: .85rem; color: var(--primary); background: var(--primary-light);
            padding: 6px 12px; border-radius: 6px; }
        .retry { margin-top: 22px; display: inline-block; padding: 9px 20px; border-radius: 6px; background: var(--primary);
            color: #fff; text-decoration: none; font-size: .9rem; }
        .retry:hover { background: #0B5ED7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        </div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        @if($endTime)
            <div class="until">Expected back by {{ $endTime->format('d M Y, h:i A') }}</div>
        @endif
        <div><a class="retry" href="{{ url()->current() }}">Try again</a></div>
    </div>
</body>
</html>
