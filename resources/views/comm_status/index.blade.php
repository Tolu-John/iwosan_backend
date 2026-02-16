<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; background: #f5f6f8; }
        .card { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; background: #e8eefc; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Delivery Status Summary (last {{ $window_hours }}h)</h2>
        <ul>
            @foreach ($by_status as $row)
                <li><span class="badge">{{ $row->delivery_status }}</span> {{ $row->total }}</li>
            @endforeach
        </ul>
    </div>

    <div class="card">
        <h2>Recent Failures</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Message ID</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($failures as $failure)
                    <tr>
                        <td>{{ $failure->id }}</td>
                        <td>{{ $failure->provider_message_id ?? 'n/a' }}</td>
                        <td>{{ $failure->delivery_status }}</td>
                        <td>{{ $failure->event_timestamp }}</td>
                        <td>{{ $failure->metadata['reason'] ?? 'n/a' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No failures in window.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
