<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Http\Request;
use Exception;

class VerifyCognitoToken
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $authHeader = $request->header('Authorization');

            if (!$authHeader) {
                return response()->json(['error' => 'Authorization header missing'], 401);
            }

            if (!str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(['error' => 'Invalid Authorization format'], 401);
            }

            $token = trim(substr($authHeader, 7));

            if (empty($token)) {
                return response()->json(['error' => 'Empty token'], 401);
            }

            // Configuración usando env() directamente
            $region = env('AWS_DEFAULT_REGION', 'us-east-1');
            $userPoolId = env('COGNITO_USER_POOL_ID');
            
            if (empty($userPoolId)) {
                throw new Exception('COGNITO_USER_POOL_ID not configured in .env file');
            }

            $issuer = "https://cognito-idp.{$region}.amazonaws.com/{$userPoolId}";

            // Obtener JWKS con manejo de errores mejorado
            $jwkUrl = "{$issuer}/.well-known/jwks.json";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            
            $jwksContent = @file_get_contents($jwkUrl, false, $context);
            
            if ($jwksContent === false) {
                $error = error_get_last();
                throw new Exception('Failed to fetch JWKS from Cognito: ' . ($error['message'] ?? 'Unknown error'));
            }

            $jwks = json_decode($jwksContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in JWKS response');
            }

            if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
                throw new Exception('Invalid JWKS format - missing keys array');
            }

            // Parsear claves
            $keys = JWK::parseKeySet($jwks);

            // Decodificar token
            $decoded = JWT::decode($token, $keys);

            // Validaciones
            if ($decoded->iss !== $issuer) {
                throw new Exception('Invalid issuer. Expected: ' . $issuer . ', Got: ' . $decoded->iss);
            }

            if (isset($decoded->exp) && $decoded->exp < time()) {
                throw new Exception('Token expired');
            }

            // Validar audience si está configurado
            $clientId = env('COGNITO_CLIENT_ID');
            if ($clientId && isset($decoded->aud) && $decoded->aud !== $clientId) {
                throw new Exception('Invalid audience. Expected: ' . $clientId . ', Got: ' . $decoded->aud);
            }

            // Validar token_use si está presente
            if (isset($decoded->token_use) && $decoded->token_use !== 'access') {
                throw new Exception('Invalid token use. Expected: access, Got: ' . $decoded->token_use);
            }

            // Agregar datos del usuario al request
            $request->attributes->add([
                'jwt_payload' => (array)$decoded,
                'cognito_user' => $decoded
            ]);

            return $next($request);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}