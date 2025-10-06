<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class DisableUserController extends Controller
{
    public function disable(Request $request, $userId)
    {
        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $user->estado = $user->estado ? 0 : 1;
        $user->save();

        return response()->json([
            'message' => $user->estado ? 'Usuario activado exitosamente' : 'Usuario deshabilitado exitosamente',
            'user' => $user
        ], 200);
    }
}
