<?php

namespace Moontec\Repository;

use Illuminate\Support\Facades\Http;

class AuthRepository
{
    public function __construct()
    {}

    function getAccessTokenSystem(): string {
        $response = Http::asForm()->post(env("API_URL") . "oauth/token", [
            'grant_type' => "client_credentials",
            'client_id' => env("CLIENT_ID"),
            'client_secret' => env("CLIENT_SECRET")            
        ]);

        $bodyToken = json_decode($response);        

        return $bodyToken->access_token;
    }
}