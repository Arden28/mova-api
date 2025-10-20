<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => (string) $this->id,
            'plate'     => $this->plate,
            'capacity'  => (int) $this->capacity,
            'name'      => $this->name,
            'type'      => $this->type,   // standard|luxury|minibus
            'status'    => $this->status, // active|maintenance|inactive
            'model'     => $this->model,
            'year'      => $this->year,
            'mileage_km'=> $this->mileage_km,
            'last_service_date'   => $this->last_service_date?->toDateString(),
            'insurance_provider'  => $this->insurance_provider,
            'insurance_policy_number' => $this->insurance_policy_number,
            'insurance_valid_until'   => $this->insurance_valid_until?->toDateString(),

            'operator_id'        => $this->operator_id,
            'assigned_driver_id' => $this->assigned_driver_id,

            // Optional related snippets
            'operator' => $this->whenLoaded('operator', fn() => [
                'id' => $this->operator->id,
                'name' => $this->operator->name,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
