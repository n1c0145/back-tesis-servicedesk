<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\DisableUserController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Ticket\TicketController;
use App\Http\Controllers\Ticket\TicketViewController;
use App\Http\Controllers\Notificaciones\NotificationController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::middleware('auth:api')->post('/change-password', [ChangePasswordController::class, 'changePassword']);

// Rutas protegidas con token 
Route::middleware('cognito')->group(function () {
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword']);
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
    Route::post('/mark-read/{userId}/{notificationId}', [NotificationController::class, 'markAsRead']);
});
