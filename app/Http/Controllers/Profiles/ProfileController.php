<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class ProfileController extends Controller
{
    public function getProfile($id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json($user, 200);
    }

    public function updateProfile(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Validación
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'puesto' => 'required|string|max:255',
            'role_id'  => 'nullable|exists:roles,id',
        ]);

        // Actualización
        $user->update([
            'nombre'   => $request->nombre,
            'apellido' => $request->apellido,
            'puesto'   => $request->puesto,
            'role_id'  => $request->role_id ?? $user->role_id,
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user'    => $user
        ], 200);
    }
    public function getAllProfiles()
    {
        $users = User::with('role')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No hay usuarios registrados'
            ], 404);
        }

        return response()->json($users, 200);
    }
}
