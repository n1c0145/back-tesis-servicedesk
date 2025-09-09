<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Auth;

class ChangePasswordController extends Controller
{
    protected $cognito;

    public function __construct()
    {
        // Configurar el cliente Cognito manualmente
        $region = env('AWS_DEFAULT_REGION', 'us-east-1');
        $key = env('AWS_ACCESS_KEY_ID');
        $secret = env('AWS_SECRET_ACCESS_KEY');
        
        $config = [
            'region' => $region,
            'version' => 'latest',
        ];
        
        // Solo agregar credenciales si están configuradas
        if ($key && $secret) {
            $config['credentials'] = new Credentials($key, $secret);
        }
        
        $this->cognito = new CognitoIdentityProviderClient($config);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        try {
            // Access token del usuario autenticado
            $accessToken = $request->bearerToken();

            if (!$accessToken) {
                return response()->json(['error' => 'Token no proporcionado'], 401);
            }

            // Llamada a Cognito para cambiar la contraseña
            $this->cognito->changePassword([
                'AccessToken' => $accessToken,
                'PreviousPassword' => $request->old_password,
                'ProposedPassword' => $request->new_password,
            ]);

            return response()->json(['message' => 'Contraseña cambiada exitosamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}