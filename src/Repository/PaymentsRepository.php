<?php

namespace Moontec\Repository;

use Moontec\Utils\RequestUtils;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use stdClass;

class PaymentsRepository
{
    protected $endpointPayments;
    function __construct(protected AuthRepository $authRepository, protected Client $client)  
    {
        $this->endpointPayments = env("API_URL");
     }

    function getPublicKey(): string
    {        
        $request = RequestUtils::createRequest($this->authRepository, null);
        $response = $this->client->request('GET', $this->endpointPayments . "api/payments/public-key", $request);        
        
        return json_decode($response->getBody())->public_key;
    }

    function getPaymentByReferenceId($referenceId)
    {        
        $request = RequestUtils::createRequest($this->authRepository, null);
        $response = $this->client->request('GET', $this->endpointPayments . "api/payments/" . $referenceId, $request);        

        return json_decode($response->getBody());
    }

    function getOrderPayment($transactionCode)
    {        
        $request = RequestUtils::createRequest($this->authRepository, null);
        $response = $this->client->request('GET', $this->endpointPayments . "api/payments/order/" . $transactionCode, $request);        

        return json_decode($response->getBody());
    }    

    function createOrder($payload): stdClass
    {        
        $request = RequestUtils::createRequest($this->authRepository, json_encode($payload));
        $response = $this->client->request('POST', $this->endpointPayments . "api/payments", $request);        

        if($response->getStatusCode() !== 200) {
            Log::channel("exception")->error("Ocorreu um erro na criação do pagamento. StatusCode: ". $response->getStatusCode() 
            ."\n ResponseBody: ".$response->getBody());

            throw new \Exception("Ocorreu um erro na criação do pagamento: ". $response->getBody());
        }

        return json_decode($response->getBody());
    }
    
    function cancelOrder($payload): stdClass
    {
        $request = RequestUtils::createRequest($this->authRepository, json_encode($payload));
        $response = $this->client->request('POST', $this->endpointPayments . "api/payments/cancel", $request);        

        if($response->getStatusCode() !== 200) {
            Log::channel("exception")->error("Ocorreu um erro no cancelamento do pagamento. StatusCode: ". $response->getStatusCode() 
            ."\n ResponseBody: ".$response->getBody());

            throw new \Exception("Ocorreu um erro no cancelamento do pagamento: ". $response->getBody());
        }

        return json_decode($response->getBody());
    }    

    function getInstallmentsFees($payload): stdClass
    {
        $request = RequestUtils::createRequest($this->authRepository, json_encode($payload));
        $response = $this->client->request('POST', $this->endpointPayments . "api/payments/fees", $request);        

        if($response->getStatusCode() !== 200) {
            Log::channel("exception")->error("Ocorreu um erro na cotação de parcelas. StatusCode: ". $response->getStatusCode() 
            ."\n ResponseBody: ".$response->getBody());

            throw new \Exception("Ocorreu um erro na cotação de parcelas: ". $response->getBody());
        }

        return json_decode($response->getBody());
    }      
}
