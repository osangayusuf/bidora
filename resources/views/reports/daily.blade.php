<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bidora Daily Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .subtitle { color: #666; margin-top: 4px; margin-bottom: 20px; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .stats td { width: 25%; padding: 10px; border: 1px solid #ddd; text-align: center; }
        .stats .label { display: block; font-size: 10px; text-transform: uppercase; color: #666; }
        .stats .value { display: block; font-size: 22px; font-weight: bold; margin-top: 4px; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; font-size: 11px; }
        table.list th { background: #f5f5f5; }
        .note { color: #888; font-size: 10px; margin-top: 6px; }
        .empty { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <h1>Bidora Daily Visitation Report</h1>
    <p class="subtitle">
        {{ ucfirst($period) }} report &mdash; {{ $range['start'] }}
        @if($range['start'] !== $range['end'])
            to {{ $range['end'] }}
        @endif
    </p>

    <table class="stats">
        <tr>
            <td><span class="label">Visits</span><span class="value">{{ number_format($visits) }}</span></td>
            <td><span class="label">Engagement</span><span class="value">{{ number_format($engagement) }}</span></td>
            <td><span class="label">Active Subscribers</span><span class="value">{{ number_format($active_count) }}</span></td>
            <td><span class="label">Non-Active Subscribers</span><span class="value">{{ number_format($non_active_count) }}</span></td>
        </tr>
    </table>

    <h2>Active Subscribers ({{ number_format($active_count) }})</h2>
    <p class="note">Current subscription status as of report generation — not scoped to the {{ $period }} window above.</p>
    @if($active_users->isEmpty())
        <p class="empty">No active users in this period.</p>
    @else
        <table class="list">
            <thead><tr><th>Name</th><th>Email</th></tr></thead>
            <tbody>
                @foreach($active_users as $user)
                    <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if($active_users_truncated)
            <p class="note">Showing the first {{ $active_users->count() }} of {{ number_format($active_count) }} — see the Users page for the full list.</p>
        @endif
    @endif

    <h2>Non-Active Subscribers ({{ number_format($non_active_count) }})</h2>
    @if($non_active_users->isEmpty())
        <p class="empty">No non-active users in this period.</p>
    @else
        <table class="list">
            <thead><tr><th>Name</th><th>Email</th></tr></thead>
            <tbody>
                @foreach($non_active_users as $user)
                    <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if($non_active_users_truncated)
            <p class="note">Showing the first {{ $non_active_users->count() }} of {{ number_format($non_active_count) }} — see the Users page for the full list.</p>
        @endif
    @endif
</body>
</html>
