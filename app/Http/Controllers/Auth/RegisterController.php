<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|unique:users,correo',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'cedula' => 'required|string|unique:users,cedula',
            'edad' => 'nullable|integer|min:0',
            'telefono' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create($request->all());

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
        ], 201);
    }
}