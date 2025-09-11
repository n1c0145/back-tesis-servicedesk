<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CognitoService;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class LoginController extends Controller
{
    protected $cognito;

    public function __construct(CognitoService $cognito)
    {
        $this->cognito = $cognito;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $user = User::where('correo', $request->correo)->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        if ($user->estado == 0) {
            return response()->json(['error' => 'Usuario deshabilitado'], 403);
        }

        $response = $this->cognito->loginUser(
            $request->correo,
            $request->password
        );

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 401);
        }

        return response()->json([
            'message' => 'Login exitoso',
            'tokens' => $response,
            'user' => $user,
        ]);
    }
}
