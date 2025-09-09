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
            $token = $this->getBearerToken($request);

            $region = env('AWS_DEFAULT_REGION', 'us-east-1');
            $userPoolId = env('COGNITO_USER_POOL_ID');
            $clientId = env('COGNITO_CLIENT_ID');
            $issuer = "https://cognito-idp.{$region}.amazonaws.com/{$userPoolId}";

            // Obtener y parsear las claves públicas de Cognito
            $jwks = json_decode(file_get_contents("{$issuer}/.well-known/jwks.json"), true);
            $keys = JWK::parseKeySet($jwks);

            // Decodificar y validar
            $decoded = JWT::decode($token, $keys);

            if ($decoded->iss !== $issuer) throw new Exception('Invalid issuer');
            if (isset($decoded->exp) && $decoded->exp < time()) throw new Exception('Token expired');
            if ($clientId && isset($decoded->aud) && $decoded->aud !== $clientId) throw new Exception('Invalid audience');
            if (isset($decoded->token_use) && $decoded->token_use !== 'access') throw new Exception('Invalid token use');

            // Adjuntar datos al request
            $request->attributes->add([
                'jwt_payload' => (array) $decoded,
                'cognito_user' => $decoded
            ]);

            return $next($request);
        } catch (Exception $e) {
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
        }
    }

    private function getBearerToken(Request $request): string
    {
        $auth = $request->header('Authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            abort(401, 'Authorization header missing or invalid');
        }
        return trim(substr($auth, 7));
    }
}
