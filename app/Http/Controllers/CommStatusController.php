<?php

namespace App\Http\Controllers;

use App\Models\CommEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommStatusController extends Controller
{
    public function summary(Request $request)
    {
        $windowHours = (int) $request->query('window_hours', 24);
        $since = Carbon::now()->subHours($windowHours);

        $byStatus = CommEvent::whereNotNull('delivery_status')
            ->selectRaw('delivery_status, COUNT(*) as total')
            ->groupBy('delivery_status')
            ->get();

        $failures = CommEvent::where('delivery_status', 'failed')
            ->where('event_timestamp', '>=', $since)
            ->orderBy('event_timestamp', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'window_hours' => $windowHours,
            'by_status' => $byStatus,
            'recent_failures' => $failures,
        ], 200);
    }
}
