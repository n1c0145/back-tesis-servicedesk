<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RestorePasswordController;
use App\Http\Controllers\Auth\DisableUserController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Ticket\TicketController;
use App\Http\Controllers\Ticket\TicketViewController;
use App\Http\Controllers\Notificaciones\NotificationController;
use App\Http\Controllers\Profiles\ProfileController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::middleware('auth:api')->post('/change-password', [RestorePasswordController::class, 'changePassword']);
Route::post('/forgot-password-code', [RestorePasswordController::class, 'sendForgotPasswordCode']);
Route::post('/reset-password', [RestorePasswordController::class, 'resetPassword']);

// Rutas protegidas con token 
Route::middleware('cognito')->group(function () {
    Route::post('/change-password', [RestorePasswordController::class, 'changePassword']);
    Route::patch('/users/disable/{userId}', [DisableUserController::class, 'disable']);
    Route::post('/create-projects', [ProjectController::class, 'store']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/delete-project/{id}', [ProjectController::class, 'disable']);
    Route::patch('/update-project/{id}', [ProjectController::class, 'update']);
    Route::post('/create-ticket', [TicketController::class, 'store']);
    Route::post('/new-thread', [TicketController::class, 'addThread']);
    Route::get('/ticket/{id}', [TicketViewController::class, 'showTicket']);
    Route::get('/notifications/{userId}', [NotificationController::class, 'allNotifications']);
    Route::get('/notifications-unread/{userId}', [NotificationController::class, 'unreadNotifications']);
    Route::post('/mark-read/{notificationId}', [NotificationController::class, 'markAsRead']);
    Route::patch('/ticket-status/{id}', [TicketController::class, 'updateStatus']);
    Route::patch('/ticket-assigned-to/{id}', [TicketController::class, 'updateAssignedTo']);
    Route::post('/close-ticket', [TicketController::class, 'closeTicket']);
    Route::get('/get-profile/{id}', [ProfileController::class, 'getProfile']);
    Route::patch('/update-profile/{id}', [ProfileController::class, 'updateProfile']);
    Route::get('/profiles', [ProfileController::class, 'getAllProfiles']);
});
