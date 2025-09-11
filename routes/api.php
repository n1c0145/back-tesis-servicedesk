<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\DisableUserController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::middleware('auth:api')->post('/change-password', [ChangePasswordController::class, 'changePassword']);

// Rutas protegidas con token 
Route::middleware('cognito')->group(function () {
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword']);
    Route::patch('/users/disable/{userId}', [DisableUserController::class, 'disable']);

  
});