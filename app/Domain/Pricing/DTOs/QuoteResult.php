<?php

namespace App\Domain\Pricing\DTOs;

class QuoteResult
{
    // Core components
    public float $base = 0.0;            // per-km * km * buses
    public float $motivation = 0.0;      // +% by vehicle
    public float $event = 0.0;           // +% by event
    public float $majorated = 0.0;       // base * (1 + motivation + event)
    public float $clientFees = 0.0;      // +4% client MM
    public float $clientRaw = 0.0;       // majorated + clientFees (pre-round)
    public float $clientRounded = 0.0;   // special rounding (up)
    public float $commission = 0.0;      // 13% of clientRounded
    public float $busBase = 0.0;         // clientRounded - commission
    public float $busFees = 0.0;         // +3.5% bus MM
    public float $busRaw = 0.0;          // busBase + busFees (pre-round)
    public float $busRounded = 0.0;      // special rounding (up)

    public array $meta = []; // debug / transparency

    public function asArray(): array
    {
        return [
            'currency'       => config('pricing.currency'),
            'breakdown'      => [
                'base'          => $this->base,
                'motivation'    => $this->motivation,
                'event'         => $this->event,
                'majorated'     => $this->majorated,
                'client_fees'   => $this->clientFees,
                'client_raw'    => $this->clientRaw,
                'client_rounded'=> $this->clientRounded,
                'commission'    => $this->commission,
                'bus_base'      => $this->busBase,
                'bus_fees'      => $this->busFees,
                'bus_raw'       => $this->busRaw,
                'bus_rounded'   => $this->busRounded,
            ],
            'meta'           => $this->meta,
            'client_payable' => $this->clientRounded,
            'bus_payable'    => $this->busRounded,
        ];
    }
}
