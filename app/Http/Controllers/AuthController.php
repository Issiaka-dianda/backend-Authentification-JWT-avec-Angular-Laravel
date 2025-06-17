<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Return the user data with a cookie for the token
        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ])->cookie(
    'auth_token',     // Cookie name
            $token,           // Cookie value
            60*24*7,          // Duration in minutes (1 week)
            '/',              // Path
            null,             // Domain
            true,             // Secure (HTTPS only)
            true              // HTTP Only (not accessible via JavaScript)
        );
    }
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        // Clear the cookie
        return response()->json(['message' => 'Logged out'])
            ->cookie('auth_token', '', -1);
    }
}
