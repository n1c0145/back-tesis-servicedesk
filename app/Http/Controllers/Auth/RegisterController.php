<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Services\CognitoService;
use Aws\Exception\AwsException;

class RegisterController extends Controller
{
    protected $cognito;

    public function __construct(CognitoService $cognito)
    {
        $this->cognito = $cognito;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|unique:users,correo',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'cedula' => 'required|string|unique:users,cedula',
            'puesto' => 'nullable|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Registrar en Cognito
        $cognitoResponse = $this->cognito->registerUser(
            $request->correo,
            $request->password
        );

        if (isset($cognitoResponse['error'])) {
            return response()->json(['error' => $cognitoResponse['error']], 500);
        }

        // Confirmar usuario automáticamente 
        try {
            $this->cognito->getClient()->adminConfirmSignUp([
                'UserPoolId' => env('COGNITO_USER_POOL_ID'),
                'Username' => $request->correo,
            ]);
        } catch (AwsException $e) {
            return response()->json(['error' => $e->getAwsErrorMessage()], 500);
        }

        // Guardar en la BD
        $user = User::create($request->only(['correo', 'nombre', 'apellido', 'cedula', 'puesto', 'role_id']));

        return response()->json([
            'message' => 'Usuario registrado en Cognito y BD exitosamente',
            'user' => $user,
            'cognito' => $cognitoResponse,
        ], 201);
    }
}
