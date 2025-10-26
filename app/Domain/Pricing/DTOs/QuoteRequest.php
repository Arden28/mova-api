<?php

namespace App\Domain\Pricing\DTOs;

use Carbon\CarbonInterface;

class QuoteRequest
{
    public function __construct(
        public string $vehicleType,     // 'hiace' | 'coaster'
        public float $distanceKm,       // positive float
        public string $eventType = 'none', // 'wedding' | 'funeral' | 'church' | 'none'
        public int $buses = 1,          // number of buses
        public ?CarbonInterface $when = null // optional, not used in v1 but kept for future rules
    ) {}
}
