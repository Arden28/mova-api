<?php

namespace App\Domain\Pricing\DTOs;

use Carbon\CarbonInterface;

class QuoteRequest
{
    public function __construct(
        // Legacy fields (still supported)
        public ?string $vehicleType = null, // 'hiace' | 'coaster'
        public int $buses = 1,

        // New: list of vehicle types (e.g. ['hiace','coaster'])
        public array $vehicleTypes = [],

        public float $distanceKm = 0.0,
        public string $eventType = 'none',
        public ?CarbonInterface $when = null
    ) {}
}
