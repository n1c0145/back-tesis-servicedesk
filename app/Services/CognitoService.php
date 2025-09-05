<?php

namespace App\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;

class CognitoService
{
    protected $client;
    protected $clientId;

    public function __construct()
    {
        $this->client = new CognitoIdentityProviderClient([
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => '2016-04-18',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $this->clientId = env('COGNITO_APP_CLIENT_ID');
    }

    public function registerUser($email, $password)
    {
        try {
            return $this->client->signUp([
                'ClientId' => $this->clientId,
                'Username' => $email,
                'Password' => $password,
                'UserAttributes' => [
                    [
                        'Name' => 'email',
                        'Value' => $email,
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            return ['error' => $e->getAwsErrorMessage()];
        }
    }
}
