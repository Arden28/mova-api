<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    // GET /api/staff?search=&status=&role=&per_page=15
    public function index(Request $request)
    {
        $q = User::query()
            ->whereIn('role', ['agent','admin']);

        if ($search = $request->string('search')->toString()) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name','like',"%{$search}%")
                   ->orWhere('email','like',"%{$search}%")
                   ->orWhere('phone','like',"%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }

        // optional: filter specific staff role (agent or admin)
        if ($role = $request->string('role')->toString()) {
            $q->whereIn('role', array_intersect(['agent','admin'], [$role]));
        }

        $perPage = max((int) $request->input('per_page', 50), 1);

        return StaffResource::collection($q->latest()->paginate($perPage));
    }

    // POST /api/staff
    public function store(StoreStaffRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // default status
        $data['status'] = $data['status'] ?? 'active';

        $staff = User::create($data);

        return new StaffResource($staff);
    }

    // GET /api/staff/{staff}
    public function show(User $staff)
    {
        $this->assertStaff($staff);
        return new StaffResource($staff);
    }

    // PUT/PATCH /api/staff/{staff}
    public function update(UpdateStaffRequest $request, User $staff)
    {
        $this->assertStaff($staff);

        $data = $request->validated();
        if (array_key_exists('password', $data)) {
            $data['password'] = $data['password']
                ? Hash::make($data['password'])
                : null;
        }

        $staff->update($data);

        return new StaffResource($staff);
    }

    // DELETE /api/staff/{staff}
    // If you prefer not to hard-delete, you can set status=inactive instead.
    public function destroy(User $staff)
    {
        $this->assertStaff($staff);
        $staff->delete(); // hard delete
        return response()->noContent();
    }

    // POST /api/staff/bulk-status  { ids:[], status:"active|inactive|suspended" }
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids'    => ['required','array','min:1'],
            'ids.*'  => ['integer','exists:users,id'],
            'status' => ['required', Rule::in(['active','inactive','suspended'])],
        ]);

        User::whereIn('id', $validated['ids'])
            ->whereIn('role', ['agent','admin'])
            ->update(['status' => $validated['status']]);

        return response()->json(['updated' => count($validated['ids'])]);
    }

    // POST /api/staff/role  { id: number, role: "agent|admin" } (promote/demote)
    public function setRole(Request $request)
    {
        $validated = $request->validate([
            'id'   => ['required','integer','exists:users,id'],
            'role' => ['required', Rule::in(['agent','admin'])],
        ]);

        $staff = User::findOrFail($validated['id']);
        $this->assertStaff($staff);

        $staff->update(['role' => $validated['role']]);

        return new StaffResource($staff);
    }

    private function assertStaff(User $staff): void
    {
        if (!in_array($staff->role, ['agent','admin'], true)) {
            abort(404); // hide non-staff users from this endpoint
        }
    }
}
