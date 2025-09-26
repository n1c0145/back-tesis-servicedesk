<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Notifications\ForgotPasswordCode;

class ChangePasswordController extends Controller
{
    protected $cognito;

    public function __construct()
    {
        $this->cognito = new CognitoIdentityProviderClient([
            'region'  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
        ]);
    }

    public function changePassword(Request $request)
    {
        // Validar la entrada del usuario
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $accessToken = $request->bearerToken();

        if (!$accessToken) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        try {
            $this->cognito->changePassword([
                'AccessToken'      => $accessToken,
                'PreviousPassword' => $request->old_password,
                'ProposedPassword' => $request->new_password,
            ]);

            return response()->json(['message' => 'Contraseña cambiada exitosamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendForgotPasswordCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|exists:users,correo',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('correo', $request->correo)->first();

        $code = rand(10000, 99999);
        $user->temporal_code = $code;
        $user->save();

        $user->notify(new ForgotPasswordCode($code));

        return response()->json([
            'message' => 'Código de recuperación enviado al correo.',
        ]);
    }
}
