<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolePermissionsRequest;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(Role::query()->with('permissions:code')->orderBy('name')->get(['id', 'name']));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = Role::query()->create(['id' => (string) Str::uuid(), 'name' => $request->validated('name')]);
        $this->audit->record($request->user()->id, 'role.created', 'role', $role->id);

        return response()->json(['id' => $role->id, 'name' => $role->name], 201);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $this->audit->record($request->user()->id, 'role.updated', 'role', $role->id);

        return response()->json(['id' => $role->id, 'name' => $role->name]);
    }

    public function destroy(Role $role, Request $request): JsonResponse
    {
        $role->delete();
        $this->audit->record($request->user()->id, 'role.deleted', 'role', $role->id);

        return response()->json(status: 204);
    }

    public function replacePermissions(RolePermissionsRequest $request, Role $role): JsonResponse
    {
        $permissions = $request->validated('permissions');
        DB::transaction(function () use ($permissions, $role): void {
            $role->permissions()->sync($permissions);
        });
        $this->audit->record($request->user()->id, 'role.permissions_changed', 'role', $role->id, ['permissions_count' => count($permissions)]);

        return response()->json(['id' => $role->id, 'permissions' => $permissions]);
    }
}
