<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest as QuoteHttpRequest;
use App\Domain\Pricing\DTOs\QuoteRequest;
use App\Domain\Pricing\Services\PricingEngine;
use Carbon\Carbon;

class QuoteController extends Controller
{
    public function __construct(private PricingEngine $engine) {}

    public function __invoke(QuoteHttpRequest $req)
    {
        $dto = new QuoteRequest(
            vehicleType: $req->string('vehicle_type'),
            distanceKm : (float) $req->input('distance_km'),
            eventType  : $req->string('event', 'none'),
            buses      : (int) ($req->input('buses', 1)),
            when       : $req->filled('when') ? Carbon::parse($req->input('when')) : null,
        );

        $quote = $this->engine->quote($dto);
        return response()->json($quote->asArray());
    }
}
