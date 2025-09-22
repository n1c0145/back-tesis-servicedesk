<?php

namespace App\Http\Controllers\Notificaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class NotificationController extends Controller
{
    public function allNotifications($userId)
    {
        $user = User::findOrFail($userId);

        $notifications = $user->notifications()->orderBy('created_at', 'desc')->get();

        return response()->json($notifications, 200);
    }
    public function unreadNotifications($userId)
    {
        $user = User::findOrFail($userId);

        $unread = $user->unreadNotifications->sortByDesc('created_at')->values();

        $count = $unread->count();

        return response()->json([
            'unread_count' => $count,
            'unread_notifications' => $unread,
        ], 200);
    }

    public function markAsRead($userId, $notificationId)
    {
        $user = User::findOrFail($userId);

        $notification = $user->notifications()->where('id', $notificationId)->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída.',
            'notification' => $notification
        ], 200);
    }
}
