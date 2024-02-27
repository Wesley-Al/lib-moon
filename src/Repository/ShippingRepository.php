<?php

namespace Moontec\Repository;

use GuzzleHttp\Client;

class ShippingRepository
{
    protected $endpointPayments;
    function __construct(protected Client $client)  
    {
        $this->endpointPayments = env("API_URL");
    }

    public function getShippingQuote($payload) 
    {       
        $request = $this->createRequest(json_encode($payload));
        $response = $this->client->request('POST', "https://api.frenet.com.br/shipping/quote", $request);                

        return json_decode($response->getBody());
    }

    protected function createRequest($body)
    {
        $token = env("FRENET_TOKEN");

        return [
            'headers' => [
                'token' => $token,
                'Aaccept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            "body" => $body
        ];
    }
}