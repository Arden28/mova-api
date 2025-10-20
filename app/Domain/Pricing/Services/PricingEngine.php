<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\DTOs\QuoteRequest;
use App\Domain\Pricing\DTOs\QuoteResult;
use App\Domain\Pricing\Support\Rounding;
use InvalidArgumentException;

class PricingEngine
{
    /**
     * Compute a full quote based on business rules from your spec.
     * Steps mirror the document’s “Étapes détaillées” exactly.
     */
    public function quote(QuoteRequest $req): QuoteResult
    {
        $cfg = config('pricing');

        $vehicle = $cfg['vehicles'][$req->vehicleType] ?? null;
        if (!$vehicle) {
            throw new InvalidArgumentException("Unknown vehicle type: {$req->vehicleType}");
        }
        $eventPct = $cfg['events'][$req->eventType] ?? null;
        if ($eventPct === null) {
            throw new InvalidArgumentException("Unknown event type: {$req->eventType}");
        }

        $perKm = (float) $vehicle['per_km'];
        $motivationPct = (float) $vehicle['motivation_percent']; // +40% or +25%  :contentReference[oaicite:12]{index=12}
        $buses = max(1, (int) $req->buses);

        $clientMM = (float) $cfg['mobile_money_client_percent']; // +4%  :contentReference[oaicite:13]{index=13}
        $commission = (float) $cfg['commission_percent'];        // -13%  :contentReference[oaicite:14]{index=14}
        $busMM = (float) $cfg['mobile_money_bus_percent'];       // +3.5% :contentReference[oaicite:15]{index=15}

        $res = new QuoteResult();

        // Step 1 — Base: per-km * km * buses
        $res->base = $perKm * max(0, $req->distanceKm) * $buses; // :contentReference[oaicite:16]{index=16}

        // Step 2 — Apply motivation + event (% of base)
        $res->motivation = $res->base * $motivationPct;          // :contentReference[oaicite:17]{index=17}
        $res->event      = $res->base * $eventPct;               // :contentReference[oaicite:18]{index=18}
        $res->majorated  = $res->base + $res->motivation + $res->event;

        // Step 3 — Client Mobile Money (+4%)
        $res->clientFees = $res->majorated * $clientMM;          // :contentReference[oaicite:19]{index=19}
        $res->clientRaw  = $res->majorated + $res->clientFees;

        // Step 4 — Round client amount (step 25 upward)
        $res->clientRounded = match ($cfg['rounding']['mode'] ?? 'step25_up') {
            'step25_up' => Rounding::step25Up($res->clientRaw),
            default     => Rounding::step25Up($res->clientRaw),
        };

        // Step 5 — Commission Móva (-13%)
        $res->commission = $res->clientRounded * $commission;    // :contentReference[oaicite:20]{index=20}
        $res->busBase    = $res->clientRounded - $res->commission;

        // Step 6 — Bus Mobile Money (+3.5%)
        $res->busFees = $res->busBase * $busMM;                  // :contentReference[oaicite:21]{index=21}
        $res->busRaw  = $res->busBase + $res->busFees;

        // Step 7 — Round bus amount (same ladder)
        $res->busRounded = Rounding::step25Up($res->busRaw);     // :contentReference[oaicite:22]{index=22}

        // meta/debugging
        $res->meta = [
            'vehicle'   => $req->vehicleType,
            'event'     => $req->eventType,
            'buses'     => $buses,
            'per_km'    => $perKm,
            'motivation_percent' => $motivationPct,
            'event_percent'      => $eventPct,
            'client_mm_percent'  => $clientMM,
            'commission_percent' => $commission,
            'bus_mm_percent'     => $busMM,
            'rounding'  => $cfg['rounding']['mode'] ?? 'step25_up',
        ];

        return $res;
    }
}
