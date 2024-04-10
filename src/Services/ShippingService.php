<?php

namespace Moontec\Services;

use Moontec\Repository\ProductRepository;
use Moontec\Repository\ShippingRepository;
use Moontec\Utils\NumberUtils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShippingService
{
    public function __construct(
        protected ShippingRepository $shippingRepository,
        protected ProductRepository $productRepository
    ) {
    }

    public function getShipping($cepClient, $products)
    {
        $services = $this->getQuote($cepClient, $products);

        foreach($services->ShippingSevicesArray as $serv)
        {
            if($serv->Error == false){
                $serv->enc = encrypt($serv->ShippingPrice);
            }
        }

        return $services;  
    }

    public function getShippingWithCode($cepClient, $products, $codeShpping)
    {
        if($codeShpping == "locale") {
            return (object)[
                "ShippingPrice" => 0,
                "DeliveryTime" => 10,
                "ServiceDescription" => "RETIRADA"
            ];       
        }else {
            $payload = $this->getQuote($cepClient, $products);   

            foreach($payload->ShippingSevicesArray as $shipping)
            {
                if($shipping->ServiceCode == $codeShpping){
                    return $shipping;                
                }
            }
        }        
    }

    private function getQuote($cepClient, $products) 
    {
        $prodCodList = [];
        foreach ($products as $item) {
            array_push($prodCodList, $item["prodCod"]);
        }

        $listProducts = DB::table(table: 'products')
            ->whereIn("products.id", $prodCodList)
            ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
            ->join("products_specifications as PSF", "products.id", "=", "PSF.prod_cod")
            ->where("PS.stock", ">", "0")
            ->get();

        $listProductsPayload = [];

        foreach ($listProducts as $prod) {
            array_push($listProductsPayload, (object)[
                "height" => $prod->height,
                "length" => $prod->length,
                "quantity" => $this->getQuantityProduct($products, $prod->prod_cod),
                "weight" => $prod->weight,
                "width" => $prod->width,

                "price" => $prod->price,
                "discont" => $prod->discont
            ]);
        }

        $payload = $this->getPayloadQuote($cepClient, $listProductsPayload);
        return $this->shippingRepository->getShippingQuote($payload);
    }

    private function getPayloadQuote($cepClient, $products)
    {
        $config = Cache::get("general")["config"];
        
        $invoiceValue = 0;
        $packageQtd = 1;

        $height = $config->package_height;
        $width = $config->package_width;
        $weightPackage = $config->package_weigth;
        $length = $config->package_length;

        $weightTotal = $weightPackage;
        
        
        $resultPackage = $height + $width + $length;

        foreach ($products as $prod) 
        {
            $result = ($prod->height + $prod->width + $prod->length) * $prod->quantity;
            $weightTotal += $prod->weight * $prod->quantity;

            if($prod->discont != null){
                $invoiceValue += NumberUtils::calcPercent($prod->price, $prod->discont) * $prod->quantity; 
            }else {
                $invoiceValue += $prod->price * $prod->quantity; 
            }        

            if($result > $resultPackage){
                $packageQtd += 1;
            }
        }

        return (object)[
            "SellerCEP" => $config->company_cep,
            "RecipientCEP" => $cepClient,
            "ShipmentInvoiceValue" => $invoiceValue,
            "ShippingServiceCode" => null,
            "ShippingItemArray" => [
                [
                    "Height" => $height,
                    "Length" => $length,
                    "Quantity" => $packageQtd,
                    "Weight" => $weightTotal,
                    "Width" => $width
                ]
            ],
            "RecipientCountry" => "BR"
        ];
    }

    private function getQuantityProduct($list, $prodCod)
    {
        $qtd = 0;        
        foreach ($list as $prod) {
            if ($prod["prodCod"] == $prodCod) {
                $qtd = intval($prod["quantity"]);
            }
        }

        return $qtd;
    }
}
