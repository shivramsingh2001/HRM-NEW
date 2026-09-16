@php($imp = session('impersonation'))
<div style="position:sticky;top:0;z-index:2000;background:#7c2d12;color:#fff;padding:8px 16px;font-size:13px;display:flex;justify-content:center;gap:16px;align-items:center">
    <span>
        <i class="fas fa-user-secret"></i>
        You are impersonating <strong>{{ auth()->user()->name ?? 'a user' }}</strong>
        (started by super admin #{{ $imp['by'] ?? '?' }}) —
        expires {{ \Illuminate\Support\Carbon::parse($imp['expires_at'])->timezone(config('app.timezone'))->format('H:i') }}
    </span>
    <form method="POST" action="{{ url('/impersonate/end') }}" style="margin:0">
        @csrf
        <button style="background:#fff;color:#7c2d12;border:0;border-radius:4px;padding:2px 10px;font-weight:600;cursor:pointer">End session</button>
    </form>
</div>
