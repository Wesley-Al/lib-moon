<?php

namespace Moontec\Utils;

class RequestUtils extends Utils
{
    public static function createRequest($authRepository, $body = null)
    {
        $token = $authRepository->getAccessTokenSystem();
            return [
                'headers' => [
                    'origin' => env("APP_URL"),
                    'client_id' => env("CLIENT_ID"),
                    'Authorization' => "Bearer " . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                "body" => $body
            ];
    }
}