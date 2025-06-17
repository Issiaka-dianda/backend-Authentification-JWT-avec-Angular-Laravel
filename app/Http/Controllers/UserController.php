<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::all();
        return response()->json([
            'users' => $users,
            'roles' => $roles
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        if ($request->roles) {
            $user->syncRoles($request->roles);
        }
        return response()->json($user->load('roles'), 201);
    }
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
        ]);
        $data = $request->only(['name', 'email']);
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }
        return response()->json($user->load('roles'));
    }
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);
        $user->syncRoles($request->roles);
        return response()->json($user->load('roles'));
    }
}
