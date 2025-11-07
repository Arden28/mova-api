<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\DTOs\QuoteRequest;
use App\Domain\Pricing\DTOs\QuoteResult;
use App\Domain\Pricing\Support\Rounding;
use InvalidArgumentException;

class PricingEngine
{
    public function quote(QuoteRequest $req): QuoteResult
    {
        $cfg = config('pricing');

        // 0) Validate event type coming from request against config
        $eventPct = $cfg['events'][$req->eventType] ?? null;
        if ($eventPct === null) {
            throw new InvalidArgumentException("Unknown event type: {$req->eventType}");
        }

        // 1) Normalize vehicles into a map: type => count
        $typeCounts = $this->normalizeVehicleCounts($req, $cfg);
        if (empty($typeCounts)) {
            throw new InvalidArgumentException('No vehicles provided.');
        }

        // 2) Percentages from pricing config
        $clientMM   = (float) $cfg['mobile_money_client_percent']; // client surcharge percent (added to client)
        $commission = (float) $cfg['commission_percent'];          // platform commission percent (deducted from clientRounded)
        $busMM      = (float) $cfg['mobile_money_bus_percent'];    // bus mobile-money withdrawal fee percent (deducted from bus)

        // 3) Distance handling with minimum billable distance
        $distance      = max(0.0, (float) $req->distanceKm);
        $minDistance   = (float) ($cfg['min_distance_km'] ?? 0.0);
        $effectiveDist = max($distance, $minDistance);

        $res = new QuoteResult();

        // 4) Per-type base and motivation; also keep global totals
        $perType = [];
        $totalBase = 0.0;
        $totalMotivation = 0.0;

        foreach ($typeCounts as $type => $count) {
            $vehicle = $cfg['vehicles'][$type] ?? null;
            if (!$vehicle) {
                throw new InvalidArgumentException("Unknown vehicle type: {$type}");
            }

            $perKm         = (float) $vehicle['per_km'];
            $motivationPct = (float) $vehicle['motivation_percent'];
            $buses         = max(1, (int) $count);

            // Base for this type over effective distance and bus count
            $base       = $perKm * $effectiveDist * $buses;
            // Motivation is a percent uplift applied to the base
            $motivation = $base * $motivationPct;

            $perType[$type] = [
                'count'              => $buses,
                'per_km'             => $perKm,
                'base'               => $base,
                'motivation_percent' => $motivationPct,
                'motivation'         => $motivation,
            ];

            $totalBase       += $base;
            $totalMotivation += $motivation;
        }

        // 5) Global aggregates before client fees/rounding
        $res->base       = $totalBase;
        $res->event      = $totalBase * $eventPct;                       // event uplift applied globally
        $res->motivation = $totalMotivation;
        $res->majorated  = $res->base + $res->motivation + $res->event;  // subtotal before client MM

        // 6) Client mobile-money surcharge and client rounding
        $res->clientFees    = $res->majorated * $clientMM;
        $res->clientRaw     = $res->majorated + $res->clientFees;        // before rounding
        $res->clientRounded = match ($cfg['rounding']['mode'] ?? 'step25_up') {
            'step25_up' => Rounding::step25Up($res->clientRaw),
            default     => Rounding::step25Up($res->clientRaw),
        };

        // 7) Platform commission and bus base
        $res->commission = $res->clientRounded * $commission;            // commission on rounded client total
        $res->busBase    = $res->clientRounded - $res->commission;       // what remains for buses before their MM fee

        // 8) Bus mobile-money fee is a withdrawal fee; subtract it
        $res->busFees = $res->busBase * $busMM;
        $res->busRaw  = $res->busBase - $res->busFees;                   // after-fee, before final rounding

        // 9) Final rounding for bus payout
        $res->busRounded = Rounding::step25Up($res->busRaw);

        /*
        * 10) Per-type allocation to ensure: sum(per-type final) == busRounded
        *     We push all global adjustments back to each type proportionally:
        *       a) Allocate event by each type share of S_i = base_i + motivation_i
        *       b) Allocate client MM by share of majorated
        *       c) Allocate client rounding uplift proportionally (clientRounded / clientRaw)
        *       d) Commission per type on the scaled client amount
        *       e) Bus MM (withdrawal) per type, then per-type bus raw
        *       f) Reconcile per-type totals to match global busRounded
        */

        // 10a) Sums for proportional allocations
        $totalS = 0.0; // sum of (base + motivation) over types
        foreach ($perType as $t => $row) {
            $totalS += ($row['base'] + $row['motivation']);
        }

        // 10b) Allocate event to each type and compute per-type majorated share
        foreach ($perType as $t => &$row) {
            $S_i = $row['base'] + $row['motivation'];
            $row['event_share']     = ($totalS > 0.0) ? ($res->event * ($S_i / $totalS)) : 0.0;
            $row['majorated_share'] = $S_i + $row['event_share']; // per-type subtotal before client MM
        }
        unset($row);

        // 10c) Allocate client MM by majorated proportion and build client_raw_share
        foreach ($perType as $t => &$row) {
            $row['client_fee_share'] = ($res->majorated > 0.0)
                ? ($res->clientFees * ($row['majorated_share'] / $res->majorated))
                : 0.0;
            $row['client_raw_share'] = $row['majorated_share'] + $row['client_fee_share'];
        }
        unset($row);

        // 10d) Allocate client rounding uplift proportionally by scaling factor
        $clientScale = ($res->clientRaw > 0.0) ? ($res->clientRounded / $res->clientRaw) : 1.0;
        foreach ($perType as $t => &$row) {
            $row['client_scaled'] = $row['client_raw_share'] * $clientScale; // per-type share of clientRounded
        }
        unset($row);

        // 10e) Commission per type (on per-type clientScaled) and per-type bus base
        foreach ($perType as $t => &$row) {
            $row['commission_share'] = $row['client_scaled'] * $commission;
            $row['bus_base_share']   = $row['client_scaled'] - $row['commission_share'];
        }
        unset($row);

        // 10f) Bus MM (withdrawal) per type and per-type bus raw
        foreach ($perType as $t => &$row) {
            $row['bus_mm_share'] = $row['bus_base_share'] * $busMM;    // fee to be deducted
            $row['bus_raw_share'] = $row['bus_base_share'] - $row['bus_mm_share'];
        }
        unset($row);

        // 10g) Reconcile per-type sum with global busRounded
        // First: sum raw shares and find the largest bucket (to absorb deltas)
        $sumBusRaw = 0.0;
        $maxKey = null; $maxVal = -INF;
        foreach ($perType as $t => $row) {
            $sumBusRaw += $row['bus_raw_share'];
            if ($row['bus_raw_share'] > $maxVal) {
                $maxVal = $row['bus_raw_share'];
                $maxKey = $t;
            }
        }

        // Global delta introduced by final rounding
        $busDelta = $res->busRounded - $sumBusRaw;

        // Initialize bus_final and push the global rounding delta to the largest share
        foreach ($perType as $t => &$row) {
            $row['bus_final'] = $row['bus_raw_share'];
        }
        if ($maxKey !== null) {
            $perType[$maxKey]['bus_final'] += $busDelta;
        }
        unset($row);

        // Make per-type values user-friendly: round to 2 decimals, then re-tighten to match busRounded exactly
        $sumFinal = 0.0;
        foreach ($perType as $t => &$row) {
            $row['bus_final'] = round($row['bus_final'], 2); // presentation rounding
            $sumFinal += $row['bus_final'];
        }
        unset($row);

        // After cent rounding, adjust any residual cents on the largest bucket
        $centsDelta = round($res->busRounded - $sumFinal, 2);
        if ($maxKey !== null && abs($centsDelta) >= 0.01) {
            $perType[$maxKey]['bus_final'] = round($perType[$maxKey]['bus_final'] + $centsDelta, 2);
        }

        // 11) Meta for transparency/debugging; vehicles now include per-type allocations and final totals
        $res->meta = [
            'distance_km_input'  => $distance,
            'min_distance_km'    => $minDistance,
            'distance_km_used'   => $effectiveDist,
            'event'              => $req->eventType,
            'event_percent'      => $eventPct,
            'vehicles'           => $perType, // enriched with per-type allocation fields, with bus_final rounded to 2 decimals
            'vehicles_total'     => array_sum(array_column($perType, 'count')),
            'client_mm_percent'  => $clientMM,
            'commission_percent' => $commission,
            'bus_mm_percent'     => $busMM,
            'rounding'           => $cfg['rounding']['mode'] ?? 'step25_up',
        ];

        return $res;
    }


    /**
     * Build a type=>count map from:
     *  - vehicles[] (array of type strings), or
     *  - legacy vehicle_type + buses, or
     *  - bus_ids[] (handled in controller → mapped to vehicles[])
     */
    private function normalizeVehicleCounts(QuoteRequest $req, array $cfg): array
    {
        $counts = [];

        if (!empty($req->vehicleTypes)) {
            foreach ($req->vehicleTypes as $t) {
                if (!isset($cfg['vehicles'][$t])) {
                    throw new InvalidArgumentException("Unknown vehicle type: {$t}");
                }
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
            return $counts;
        }

        if ($req->vehicleType) {
            if (!isset($cfg['vehicles'][$req->vehicleType])) {
                throw new InvalidArgumentException("Unknown vehicle type: {$req->vehicleType}");
            }
            $counts[$req->vehicleType] = max(1, (int) $req->buses);
        }

        return $counts;
    }
}
