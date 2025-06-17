<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions',

        ]);
        $permission = Permission::create(['name' => $request->name,'guard_name' => 'web']);
        return response()->json($permission, 201);
    }
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);
        $permission->update($request->only('name'));
        return response()->json($permission);
    }
    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->json(null, 204);
    }
}
