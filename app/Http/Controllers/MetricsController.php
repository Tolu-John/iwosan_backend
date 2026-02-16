<?php

namespace App\Http\Controllers;

use App\Models\MetricDailySummary;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function daily(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $role = $request->query('role');
        $ownerType = $request->query('owner_type');
        $ownerId = $request->query('owner_id');

        $query = MetricDailySummary::query();

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }
        if ($role) {
            $query->where('actor_role', $role);
        }
        if ($ownerType) {
            $query->where('owner_type', $ownerType);
        }
        if ($ownerId) {
            $query->where('owner_id', (int) $ownerId);
        }

        $results = $query->orderBy('date', 'asc')->get();

        return response([
            'filters' => [
                'from' => $from,
                'to' => $to,
                'role' => $role,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ],
            'results' => $results,
        ], 200);
    }
}
