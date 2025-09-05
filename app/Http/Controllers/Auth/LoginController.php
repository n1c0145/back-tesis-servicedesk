<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CognitoService;
use Illuminate\Support\Facades\Validator;

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

        $response = $this->cognito->loginUser(
            $request->correo,
            $request->password
        );

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 401);
        }

        return response()->json([
            'message' => 'Login exitoso',
            'tokens' => $response
        ]);
    }
}
