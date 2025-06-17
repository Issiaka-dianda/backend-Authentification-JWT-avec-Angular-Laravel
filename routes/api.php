<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ClaudeController;
use App\Http\Controllers\OpenAIController;


// Routes publiques
Route::post('/login', [AuthController::class, 'login']);

Route::post('/ask-openai', [OpenAIController::class, 'askOpenAI']);

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    // Routes protégées par rôles/permissions
  //  Route::middleware(['role:admin'])->group(function () 
  Route::group(['middleware' => ['role:Admin']], function (){
        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRole']);
        // Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);

        Route::post('/ask-claude', [ClaudeController::class, 'askClaude']);

        

 });
});
