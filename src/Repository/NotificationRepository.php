<?php

namespace Moontec\Repository;

use Moontec\Utils\RequestUtils;
use GuzzleHttp\Client;

class NotificationRepository
{
    protected $endpointPayments;
    function __construct(protected AuthRepository $authRepository, protected Client $client)  
    {
        $this->endpointPayments = env("API_URL");
     }

    public function getNotification($notificationCode) 
    {       
        $request = RequestUtils::createRequest($this->authRepository);
        $response = $this->client->request('GET', $this->endpointPayments . "api/notification/" . $notificationCode, $request);        

        return json_decode($response->getBody());
    }    
}