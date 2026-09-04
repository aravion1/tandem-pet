<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('activation/request', [AuthController::class, 'requestActivation']);
    Route::post('activation/confirm', [AuthController::class, 'confirmActivation']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/reset/request', [AuthController::class, 'requestPasswordReset']);
    Route::post('password/reset/confirm', [AuthController::class, 'confirmPasswordReset']);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'currentUser']);
    Route::post('users/import', [ResidentController::class, 'import'])->middleware('permission:users.manage');
    Route::patch('users/{user}/phone', [ResidentController::class, 'changePhone'])->middleware('permission:users.manage');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.manage');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');
    Route::put('roles/{role}/permissions', [RoleController::class, 'replacePermissions'])->middleware('permission:roles.manage');
});
