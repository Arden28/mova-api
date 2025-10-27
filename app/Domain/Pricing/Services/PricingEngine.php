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

        // Validate event
        $eventPct = $cfg['events'][$req->eventType] ?? null;
        if ($eventPct === null) {
            throw new InvalidArgumentException("Unknown event type: {$req->eventType}");
        }

        // Normalize vehicles into a type=>count map
        $typeCounts = $this->normalizeVehicleCounts($req, $cfg);

        if (empty($typeCounts)) {
            throw new InvalidArgumentException('No vehicles provided.');
        }

        $clientMM   = (float) $cfg['mobile_money_client_percent'];
        $commission = (float) $cfg['commission_percent'];
        $busMM      = (float) $cfg['mobile_money_bus_percent'];

        $distance = max(0.0, (float) $req->distanceKm);

        $res = new QuoteResult();

        // --- Step 1: Base per type, sum ---
        $perType = [];
        $totalBase = 0.0;
        $totalMotivation = 0.0;

        foreach ($typeCounts as $type => $count) {
            $vehicle = $cfg['vehicles'][$type] ?? null;
            if (!$vehicle) {
                throw new InvalidArgumentException("Unknown vehicle type: {$type}");
            }

            $perKm          = (float) $vehicle['per_km'];
            $motivationPct  = (float) $vehicle['motivation_percent'];
            $buses          = max(1, (int) $count);

            $base = $perKm * $distance * $buses;
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

        $res->base        = $totalBase;

        // --- Step 2: Event % is applied on the global base only (keeps business logic consistent) ---
        $res->event       = $totalBase * $eventPct;

        // --- Step 3: Majorated (base + per-type motivation + event) ---
        $res->motivation  = $totalMotivation;
        $res->majorated   = $res->base + $res->motivation + $res->event;

        // --- Step 4: Client MM (+4%) ---
        $res->clientFees  = $res->majorated * $clientMM;
        $res->clientRaw   = $res->majorated + $res->clientFees;

        // --- Step 5: Round client amount ---
        $res->clientRounded = match ($cfg['rounding']['mode'] ?? 'step25_up') {
            'step25_up' => Rounding::step25Up($res->clientRaw),
            default     => Rounding::step25Up($res->clientRaw),
        };

        // --- Step 6: Commission (-13%) ---
        $res->commission = $res->clientRounded * $commission;
        $res->busBase    = $res->clientRounded - $res->commission;

        // --- Step 7: Bus MM (+3.5%) ---
        $res->busFees = $res->busBase * $busMM;
        $res->busRaw  = $res->busBase + $res->busFees;

        // --- Step 8: Round bus amount ---
        $res->busRounded = Rounding::step25Up($res->busRaw);

        // Meta
        $res->meta = [
            'distance_km'        => $distance,
            'event'              => $req->eventType,
            'event_percent'      => $eventPct,
            'vehicles'           => $perType, // detailed per-type
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
     *  - bus_ids[] (look up types in DB) — handled in controller, which maps to vehicles[].
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
