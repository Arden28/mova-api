<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    // GET /api/reservations?search=&status=&date_from=&date_to=&bus_id=&with=buses&trashed=with|only|without&order_by=&order_dir=&per_page=
    public function index(Request $request)
    {
        $q = Reservation::query();

        // soft deletes filter
        $trashed = $request->query('trashed'); // with|only|without
        if ($trashed === 'with')      $q->withTrashed();
        elseif ($trashed === 'only')  $q->onlyTrashed();

        // eager load
        if ($with = $request->query('with')) {
            $rels = collect(explode(',', $with))->intersect(['buses'])->all();
            if ($rels) $q->with($rels);
        }

        // search: code, pax name/phone, origins/destinations
        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('code', 'like', "%{$search}%")
                   ->orWhere('passenger_name', 'like', "%{$search}%")
                   ->orWhere('passenger_phone', 'like', "%{$search}%")
                   ->orWhere('from_location', 'like', "%{$search}%")
                   ->orWhere('to_location', 'like', "%{$search}%");
            });
        }

        // filters
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        if ($dateFrom) $q->whereDate('trip_date', '>=', $dateFrom);
        if ($dateTo)   $q->whereDate('trip_date', '<=', $dateTo);

        if ($busId = $request->query('bus_id')) {
            $q->whereHas('buses', fn($bq) => $bq->where('buses.id', $busId));
        }

        // ordering
        $orderBy = in_array($request->query('order_by'), [
            'created_at','updated_at','trip_date','price_total','seats','status'
        ], true) ? $request->query('order_by') : 'trip_date';

        $orderDir = $request->query('order_dir') === 'asc' ? 'asc' : 'desc';
        $q->orderBy($orderBy, $orderDir);

        $perPage = max((int) $request->query('per_page', 15), 1);

        return ReservationResource::collection($q->paginate($perPage));
    }

    // POST /api/reservations
    public function store(StoreReservationRequest $request)
    {
        $data = $request->validated();

        Log::info('StoreReservation validated data', ['data' => $data]);

        $busIds = $data['bus_ids'] ?? null;
        unset($data['bus_ids']);

        $reservation = Reservation::create($data);

        if (is_array($busIds)) {
            $reservation->buses()->sync(array_values($busIds));
        }

        return (new ReservationResource($reservation->load('buses')))
            ->response()
            ->setStatusCode(201);
    }

    // GET /api/reservations/{reservation}
    public function show(Reservation $reservation)
    {
        $reservation->load('buses');
        return new ReservationResource($reservation);
    }

    // PUT/PATCH /api/reservations/{reservation}
    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        $data   = $request->validated();
        Log::info('UpdateReservation validated data', ['data' => $data]);
        $busIds = array_key_exists('bus_ids', $data) ? ($data['bus_ids'] ?? null) : null;
        unset($data['bus_ids']);

        $reservation->update($data);

        if ($busIds !== null) {
            // If provided, replace associations (sync). If omitted, leave as-is.
            $reservation->buses()->sync(array_values($busIds));
        }


        Log::info('UpdateReservation validated payload', [
            'reservation_id' => $reservation->id,
            'data' => $data,
            'bus_ids' => $busIds,
        ]);

        return new ReservationResource($reservation->load('buses'));
    }

    // DELETE /api/reservations/{reservation}   (soft delete)
    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return response()->noContent();
    }

    // POST /api/reservations/{reservation}/restore
    public function restore(string $reservation)
    {
        $model = Reservation::onlyTrashed()->findOrFail($reservation);
        $model->restore();
        return new ReservationResource($model->load('buses'));
    }

    // POST /api/reservations/{reservation}/status  { status: pending|confirmed|cancelled }
    public function setStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending','confirmed','cancelled'])],
        ]);

        $reservation->update(['status' => $validated['status']]);
        return new ReservationResource($reservation);
    }

    // POST /api/reservations/{reservation}/sync-buses { bus_ids: [uuid,...] }
    public function syncBuses(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'bus_ids'   => ['required','array','min:0'],
            'bus_ids.*' => ['uuid','distinct','exists:buses,id'],
        ]);

        $reservation->buses()->sync($validated['bus_ids']);
        return new ReservationResource($reservation->load('buses'));
    }

    // POST /api/reservations/{reservation}/attach-bus { bus_id: uuid }
    public function attachBus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'bus_id' => ['required','uuid','exists:buses,id'],
        ]);

        $reservation->buses()->syncWithoutDetaching([$validated['bus_id']]);
        return new ReservationResource($reservation->load('buses'));
    }

    // POST /api/reservations/{reservation}/detach-bus { bus_id: uuid }
    public function detachBus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'bus_id' => ['required','uuid','exists:buses,id'],
        ]);

        $reservation->buses()->detach($validated['bus_id']);
        return new ReservationResource($reservation->load('buses'));
    }

    // POST /api/reservations/bulk-status { ids: [uuid,...], status: pending|confirmed|cancelled }
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids'    => ['required','array','min:1'],
            'ids.*'  => ['uuid','exists:reservations,id'],
            'status' => ['required', Rule::in(['pending','confirmed','cancelled'])],
        ]);

        $count = Reservation::whereIn('id', $validated['ids'])
            ->update(['status' => $validated['status']]);

        return response()->json(['updated' => $count]);
    }
}
