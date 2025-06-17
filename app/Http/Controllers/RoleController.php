<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles',
        ]);
        $role = Role::create(['name' => $request->name,'guard_name' => 'web']);
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }
        return response()->json($role->load('permissions'), 201);
    }
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $role->id,
        ]);
        $role->update($request->only('name'));
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        return response()->json($role->load('permissions'));
    }
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, 204);
    }
    public function assignPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);
        $role->syncPermissions($request->permissions);
        return response()->json($role->load('permissions'));
    }
}
