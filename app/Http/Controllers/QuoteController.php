<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest as QuoteHttpRequest;
use App\Domain\Pricing\DTOs\QuoteRequest;
use App\Domain\Pricing\Services\PricingEngine;
use App\Models\Bus;
use Carbon\Carbon;

class QuoteController extends Controller
{
    public function __construct(private PricingEngine $engine) {}

    public function __invoke(QuoteHttpRequest $req)
    {
        // Priority: bus_ids[] > vehicles_map{} > vehicles[] > legacy vehicle_type+buses
        $vehicleTypes = [];

        if ($req->filled('bus_ids')) {
            $buses = Bus::query()
                ->whereIn('id', $req->input('bus_ids', []))
                ->get(['id','type']);

            foreach ($buses as $bus) {
                if ($bus->type) {
                    $vehicleTypes[] = $bus->type;
                }
            }
        } elseif ($req->filled('vehicles_map')) {
            /** @var array<string,int> $map */
            $map = (array) $req->input('vehicles_map');
            foreach ($map as $type => $count) {
                $count = max(1, (int) $count);
                for ($i = 0; $i < $count; $i++) {
                    $vehicleTypes[] = (string) $type;
                }
            }
        } elseif ($req->filled('vehicles')) {
            $vehicleTypes = array_values((array) $req->input('vehicles'));
        }

        $dto = new QuoteRequest(
            vehicleType : $req->filled('vehicle_type') ? (string) $req->input('vehicle_type') : null,
            buses       : (int) $req->input('buses', 1),
            vehicleTypes: $vehicleTypes,
            distanceKm  : (float) $req->input('distance_km'),
            eventType   : (string) $req->input('event', 'none'),
            when        : $req->filled('when') ? Carbon::parse($req->input('when')) : null,
        );

        $quote = $this->engine->quote($dto);
        return response()->json($quote->asArray());
    }

}
