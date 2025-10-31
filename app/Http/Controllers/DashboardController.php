<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Reservation;

class DashboardController extends Controller
{
    public function cards(Request $req)
    {
        // range: 7d|30d|90d
        $range = in_array($req->get('range'), ['7d','30d','90d']) ? $req->get('range') : '30d';
        $days  = (int) rtrim($range, 'd');

        $end   = Carbon::now();                 // set app timezone to Africa/Nairobi in config/app.php
        $start = (clone $end)->subDays($days);

        $prevEnd   = (clone $start);
        $prevStart = (clone $prevEnd)->subDays($days);

        // totals (window based on trip_date for business relevance)
        $currTotal = Reservation::whereNull('deleted_at')
            ->whereBetween('trip_date', [$start, $end])
            ->count();

        $prevTotal = Reservation::whereNull('deleted_at')
            ->whereBetween('trip_date', [$prevStart, $prevEnd])
            ->count();

        $totalDeltaPct = $this->pctDelta($currTotal, $prevTotal);

        // planned (from today forward)
        $today = Carbon::today();
        $planned = Reservation::whereNull('deleted_at')
            ->where('trip_date', '>=', $today)
            ->count();

        $prevPlanned = Reservation::whereNull('deleted_at')
            ->whereBetween('trip_date', [$today->copy()->subDays($days), $today])
            ->count();

        $plannedDelta = $this->pctDelta($planned, $prevPlanned);

        // gross revenue (sum price_total in window)
        $gross = (float) Reservation::whereNull('deleted_at')
            ->whereBetween('trip_date', [$start, $end])
            ->sum('price_total');

        $prevGross = (float) Reservation::whereNull('deleted_at')
            ->whereBetween('trip_date', [$prevStart, $prevEnd])
            ->sum('price_total');

        $grossDelta = $this->pctDelta($gross, $prevGross);

        // partners & payouts due
        // Uses reservation_buses pivot; confirmed trips in window
        // due formula ≈ clientRounded* (1 - commission) * (1 + busMM)
        $commission = (float) config('pricing.commission_percent', 0.13);
        $busMM      = (float) config('pricing.mobile_money_bus_percent', 0.035);

        $hasOperator = Schema::hasColumn('buses', 'operator_id');

        $selectGroup = $hasOperator
            ? 'b.operator_id'
            : 'rb.bus_id'; // fallback: group by bus if no operators yet

        $dueRows = DB::table('reservations as r')
            ->join('reservation_buses as rb', 'rb.reservation_id', '=', 'r.id')
            ->join('buses as b', 'b.id', '=', 'rb.bus_id')
            ->whereNull('r.deleted_at')
            ->where('r.status', 'confirmed')
            ->whereBetween('r.trip_date', [$start, $end])
            ->selectRaw("
                {$selectGroup} as grp,
                SUM( (COALESCE(r.price_total,0) * (1 - ?)) * (1 + ?) ) as due_amount
            ", [$commission, $busMM])
            ->groupBy('grp')
            ->get();

        // operators count = distinct non-null operator_id (or distinct buses if no operator column)
        $operatorsCount = $hasOperator
            ? $dueRows->filter(fn($r) => !is_null($r->grp))->count()
            : $dueRows->count();

        $dueTotal = (float) $dueRows->sum('due_amount');

        return response()->json([
            'range' => $range,
            'totals' => [
                'reservations' => [
                    'value'     => $this->int($currTotal),
                    'delta_pct' => $totalDeltaPct,
                ],
                'planned' => [
                    'value'     => $this->int($planned),
                    'delta_pct' => $plannedDelta,
                ],
                'gross_revenue' => [
                    'value'     => round($gross, 0),
                    'delta_pct' => $grossDelta,
                    'currency'  => config('pricing.currency', 'XAF'),
                ],
                'partners_due' => [
                    'operators'  => $operatorsCount,
                    'due_amount' => round($dueTotal, 0),
                    'currency'   => config('pricing.currency', 'XAF'),
                    // simple illustrative trend (vs previous window – here omitted for simplicity)
                    'delta_pct'  => $this->pctDelta($dueTotal, 0),
                ],
            ],
        ]);
    }

    public function series(Request $req)
    {
        $range = in_array($req->get('range'), ['7d','30d','90d']) ? $req->get('range') : '30d';
        $days  = (int) rtrim($range, 'd');

        $end   = Carbon::now();
        $start = (clone $end)->subDays($days - 1)->startOfDay();

        // bucket by DATE(trip_date)
        $rows = DB::table('reservations')
            ->whereNull('deleted_at')
            ->whereBetween('trip_date', [$start, $end])
            ->selectRaw("
                DATE(trip_date) as d,
                COUNT(*) as reservations,
                SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) as confirmed
            ")
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $data = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $data[] = [
                'date'          => $key,
                'reservations'  => (int) ($row->reservations ?? 0),
                'confirmées'    => (int) ($row->confirmed ?? 0),
            ];
            $cursor->addDay();
        }

        return response()->json([
            'range' => $range,
            'data'  => $data,
        ]);
    }

    private function pctDelta($curr, $prev): float
    {
        $c = (float) $curr;
        $p = (float) $prev;
        if ($p == 0.0) return $c > 0 ? 100.0 : 0.0;
        return round((($c - $p) / $p) * 100, 1);
    }

    private function int($v): int { return (int) round((float) $v); }
}
