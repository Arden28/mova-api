<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusRequest;
use App\Http\Requests\UpdateBusRequest;
use App\Http\Resources\BusResource;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
    // GET /api/buses?search=&status=&type=&operator_id=&driver_id=&year_min=&year_max=&service_before=&insurance_before=&per_page=15&with=operator,driver&order_by=created_at&order_dir=desc
    public function index(Request $request)
    {
        $q = Bus::query();

        // eager loads
        if ($with = $request->query('with')) {
            $relations = collect(explode(',', $with))
                ->intersect(['operator','driver'])
                ->all();
            if ($relations) $q->with($relations);
        }

        // search across common fields
        if ($search = trim((string) $request->query('search',''))) {
            $q->where(function($qq) use ($search) {
                $qq->where('plate','like',"%{$search}%")
                   ->orWhere('name','like',"%{$search}%")
                   ->orWhere('model','like',"%{$search}%");
            });
        }

        // filters
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($op = $request->query('operator_id')) {
            $q->where('operator_id', $op);
        }
        if ($drv = $request->query('driver_id')) {
            $q->where('assigned_driver_id', $drv);
        }
        if ($min = $request->query('year_min')) {
            $q->where('year', '>=', (int)$min);
        }
        if ($max = $request->query('year_max')) {
            $q->where('year', '<=', (int)$max);
        }
        if ($svcBefore = $request->query('service_before')) {
            $q->whereDate('last_service_date', '<=', $svcBefore);
        }
        if ($insBefore = $request->query('insurance_before')) {
            $q->whereDate('insurance_valid_until', '<=', $insBefore);
        }

        // ordering
        $orderBy = in_array($request->query('order_by'), [
            'created_at','updated_at','plate','status','type','year','mileage_km'
        ], true) ? $request->query('order_by') : 'created_at';

        $orderDir = $request->query('order_dir') === 'asc' ? 'asc' : 'desc';
        $q->orderBy($orderBy, $orderDir);

        $perPage = max((int)$request->query('per_page', 15), 1);

        return BusResource::collection($q->paginate($perPage));
    }

    // POST /api/buses
    public function store(StoreBusRequest $request)
    {
        $data = $request->validated();
        // ensure UUID id
        // $data['id'] = (string) Str::uuid();
        // defaults
        $data['status'] = $data['status'] ?? 'active';

        $bus = Bus::create($data);

        return new BusResource($bus->load(['operator','driver']));
    }

    // GET /api/buses/{bus}
    public function show(Bus $bus)
    {
        $bus->load(['operator','driver']);
        return new BusResource($bus);
    }

    // PUT/PATCH /api/buses/{bus}
    public function update(UpdateBusRequest $request, Bus $bus)
    {
        $bus->update($request->validated());
        return new BusResource($bus->load(['operator','driver']));
    }

    // DELETE /api/buses/{bus}
    public function destroy(Bus $bus)
    {
        $bus->delete(); // hard delete (you can switch to SoftDeletes if needed)
        return response()->noContent();
    }

    // POST /api/buses/{bus}/status  { status: active|maintenance|inactive }
    public function setStatus(Request $request, Bus $bus)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active','maintenance','inactive'])],
        ]);

        $bus->update(['status' => $validated['status']]);
        return new BusResource($bus);
    }

    // POST /api/buses/{bus}/assign-driver  { user_id: uuid|null }
    public function assignDriver(Request $request, Bus $bus)
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                Rule::exists('users','id')->where(fn($q)=>$q->where('role','driver')),
            ],
        ]);

        $bus->update(['assigned_driver_id' => $validated['user_id'] ?? null]);

        return new BusResource($bus->load('driver'));
    }

    // POST /api/buses/{bus}/set-operator  { user_id: uuid|null }
    public function setOperator(Request $request, Bus $bus)
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                Rule::exists('users','id')->where(fn($q)=>$q->whereIn('role',['owner','admin'])),
            ],
        ]);

        $bus->update(['operator_id' => $validated['user_id'] ?? null]);

        return new BusResource($bus->load('operator'));
    }

    // POST /api/buses/bulk-status  { ids: [uuid,...], status: active|maintenance|inactive }
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids'    => ['required','array','min:1'],
            'ids.*'  => ['uuid','exists:buses,id'],
            'status' => ['required', Rule::in(['active','maintenance','inactive'])],
        ]);

        $count = Bus::whereIn('id', $validated['ids'])
            ->update(['status' => $validated['status']]);

        return response()->json(['updated' => $count]);
    }
}
