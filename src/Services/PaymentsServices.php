<?php

namespace Moontec\Services;

use Moontec\Enums\OrderStatus;
use Moontec\Enums\PaymentStatus;
use Moontec\Repository\PaymentsRepository;
use Moontec\Repository\ProductRepository;
use Moontec\Utils\NumberUtils;
use Moontec\Utils\PaymentsUtils;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentsServices
{
    public function __construct(
        protected PaymentsRepository $paymentsRepository,
        protected ProductRepository $productRepository,
        protected ShippingService $shippingService
    ) {
    }

    function getPublicKey(): string
    {
        return $this->paymentsRepository->getPublicKey();
    }

    function cancelOrder($orderId)
    {
        try {
            DB::beginTransaction();

            $order = DB::table('orders')->select(["charge_id", "total", "payment_status", "transaction_code", "reversed"])->where("id", "=", $orderId)->first();
            $paymentStatus = PaymentStatus::from($order->payment_status);

            if ($paymentStatus == PaymentStatus::DISPONIVEL || $paymentStatus == PaymentStatus::PAGA || $paymentStatus == PaymentStatus::CANCELADA) {
                $chargeId = $order->charge_id;

                if ($chargeId == null) {
                    $orderBank = $this->paymentsRepository->getOrderPayment($order->transaction_code);

                    if ($orderBank->charges != null) {
                        $chargeId = $orderBank->charges[0]->id;
                    } else {
                        Log::channel("information")->info("PaymentsServices.cancelOrder O pagamento na instituição ainda não permite estorno: " . $orderId);
                        return false;
                    }
                }

                $payload = (object)[
                    "chargeId" => $chargeId,
                    "price" => $this->formatPricePayload($order->total),
                ];

                Log::channel("information")->info("PaymentsServices.cancelOrder Estornando pagamento na instituição: " . $orderId);
                $responseCancel = $this->paymentsRepository->cancelOrder($payload);

                if ($responseCancel->status == "CANCELED") {
                    Log::channel("information")->info("PaymentsServices.cancelOrder Pagamento Estonado com sucesso na instituição: " . $orderId);

                    $this->refundOrder($orderId, $chargeId, (bool)$order->reversed);

                    DB::commit();
                    return true;
                } else {
                    Log::channel("information")->info("PaymentsServices.cancelOrder Não foi possivel realizar o estorno na instituição: " . $orderId);
                    return false;
                }
            } else {
                Log::channel("information")->info("PaymentsServices.cancelOrder O status do pagamento na instituição não permite estorno: " . $orderId);
                return false;
            }
        } catch (Exception $error) {
            DB::rollBack();
            Log::channel("exception")->error("PaymentsServices.cancelOrder Não foi possivel realizar o processo de estorno para o pagamento:" . $orderId . ". Erro: " . $error->getMessage());

            return false;
        }
    }

    function refundOrder($orderId, $chargeId, $reversed)
    {
        if (!$reversed) {
            $productsOrder = DB::table("order_products")
                ->join("products_stock", "prod_cod", "=", "product_id")
                ->where("order_id", "=", $orderId)
                ->get();

            Log::channel("information")->info("PaymentsServices.refundOrder Iniciando retorno do estoque dos produtos: " . $orderId);
            foreach ($productsOrder as $product) {
                DB::table("products_stock")
                    ->where("prod_cod", "=", $product->product_id)
                    ->update([
                        "stock" => ($product->stock + $product->quantity)
                    ]);
            }

            Log::channel("information")->info("PaymentsServices.refundOrder Iniciando atualizacao do status do pedido para CANCELADO: " . $orderId);
            DB::table("orders")
                ->where("id", "=", $orderId)
                ->update([
                    "payment_status" => PaymentStatus::CANCELADA,
                    "status" => OrderStatus::CANCELADO,
                    "update_at" => now(),
                    "charge_id" => $chargeId,
                    "reversed" => true
                ]);
        } else {
            Log::channel("information")->info("PaymentsServices.refundOrder O Pedido ja foi estornado: " . $orderId);
        }
    }

    function getInstallmentsFees($totalPrice, $creditBin)
    {
        $config = Cache::get("general")["config"];
        $payload = (object)[
            "notFees" => $config->instalments_not_fees,
            "maxInstalments" => $config->max_instalments,
            "creditBin" => $creditBin,
            "price" => $this->formatPricePayload($totalPrice)
        ];

        $result = $this->paymentsRepository->getInstallmentsFees($payload);

        $arrayCard = json_decode(json_encode($result->payment_methods->credit_card), true);
        $instalmentsFees = null;

        foreach ($arrayCard as $bankResult) {
            $instalmentsFees = $bankResult["installment_plans"];
            break;
        }

        return $instalmentsFees;
    }

    function createOrder(Request $request)
    {
        DB::beginTransaction();
        $dataPayment = null;
        $chargeId = null;

        try {
            $typePayment = $request->get("typePayment");
            $payload = (array)json_decode($request->get("payload"));
            $itemsPayload = array();
            $itemsOrder = array();
            $itemsShipping = array();
            $subTotal = 0;
            $total = 0;
            $totalDiscont = 0;
            $quantity = 0;

            Log::channel("information")->info('PaymentsServices.createOrder Iniciando busca dos produtos do carrinho.');
            $listProducts = $this->getProductsVariables($payload);            

            if (sizeof($listProducts) == 0) {
                throw new Exception("PaymentsServices.createOrder Não é possivel realizar o pagamento sem produtos.");
            }

            foreach ($listProducts->all() as $product) {
                $productCart = $this->getProductPayload($product->stock_cod, $payload);

                if ($productCart == null) {
                    throw new Exception("PaymentsServices.createOrder O Produto " . $product->prod_cod . " não foi encontrado na base de dados ou não possui estoque o suficiente.");
                } else {
                    $subTotal += $product->price * $productCart->qtd;
                    $totalDiscont += NumberUtils::calcDiscont($product->price, $product->discont) * $productCart->qtd;
                    $quantity += $productCart->qtd;

                    array_push($itemsPayload,  [
                        "reference_id" => $product->prod_cod."-".$product->variable_cod."-".$product->stock_cod,
                        "name" => $product->name.$product->variable_name.$product->stock_name,
                        "quantity" => $productCart->qtd,
                        "unit_amount" => $this->formatPricePayload(NumberUtils::calcPercent($product->price, $product->discont)),
                    ]);

                    array_push($itemsOrder,  (object)[
                        "product_id" => $product->prod_cod,
                        "variable_cod" => $product->variable_cod,
                        "stock_cod" => $product->stock_cod,

                        "imgProd" => $product->img_list != null ? explode(",", $product->img_list)[0] : "",
                        "prodName" => $product->name,
                        "variableName" => $product->variable_name,                        
                        "stockName" => $product->stock_name,
                        
                        "phrase" => isset($productCart->phrase) ? $productCart->phrase : null,
                        "quantity" => $productCart->qtd,
                        "total" => NumberUtils::calcPercent($product->price, $product->discont) * $productCart->qtd,
                        "subtotal" => $product->price * $productCart->qtd,
                        "price_unit" => $product->price,
                        "discont" => floatval("0" . $product->discont ?? 0),
                        "order_id" => null,
                        "stock" => $product->stock
                    ]);

                    array_push($itemsShipping, [
                        "prodCod" => $product->prod_cod,
                        "variableCod" => $product->variable_cod,
                        "stockCod" => $product->stock_cod,
                        "quantity" => $productCart->qtd
                    ]);
                }
            }

            Log::channel("information")->info('PaymentsServices.createOrder Realizando cotação do frete. User: ' . Auth::user()->id);
            $payloadService = $this->shippingService->getShippingWithCode($request->get("cep"), $itemsShipping, $request->get("shipping"));

            $total += ($subTotal - $totalDiscont) + $payloadService->ShippingPrice;
            $totalFees = 0;
            $dataOrder = $this->createPayload($request, $itemsPayload, $total, $typePayment);

            if ($typePayment == "CARD") {
                $charge = (object)$dataOrder->charges[0];

                if (array_key_exists("fees", $charge->amount)) {
                    $totalFees = floatval(preg_replace('/(\d{2})$/', ".$1", str($charge->amount["fees"]["buyer"]["interest"]["total"])));
                }

                $total = floatval(preg_replace('/(\d{2})$/', ".$1", str($charge->amount["value"])));
            }

            Log::channel("information")->info('PaymentsServices.createOrder Iniciando chamada da API para realizar o pagamento. User: ' . Auth::user()->id);
            $dataPayment = $this->paymentsRepository->createOrder($dataOrder);

            //TRANSAÇÕES DO BANCO DE DADOS
            Log::channel("information")->info('PaymentsServices.createOrder Realizando inserção de dados do frete. User: ' . Auth::user()->id);
            $shippingId = $this->insertShipping($request, $payloadService);

            if ($typePayment == "CARD") {
                $chargeId = $dataPayment->charges[0]->id;
            }

            Log::channel("information")->info('PaymentsServices.createOrder Realizando inserção de dados do pagamento. User: ' . Auth::user()->id);
            $orderId = DB::table("orders")
                ->insertGetId([
                    "status" => OrderStatus::PENDENTE_PAGAMENTO,
                    "payment_status" => PaymentStatus::AGUARDANDO_PAGAMENTO,
                    "total" => floatval($total),
                    "total_fees" => $totalFees,
                    "subtotal" => floatval($subTotal),
                    "quantity" => $quantity,
                    "instalments" => intval($request->get("installments")) ?? 1,
                    "shipping_id" => $shippingId,
                    "method_payment_id" => $typePayment == "CARD" ? 1 : 2,
                    "user_id" => Auth::user()->id,
                    "referency_id" => $dataPayment->reference_id,

                    "transaction_code" => $dataPayment->id,

                    "charge_id" => $chargeId
                ]);

            $this->saveLogPaymentCard($typePayment, $orderId, $dataPayment);
            $this->savePixQrCode($typePayment, $orderId, $dataPayment);

            Log::channel("information")->info('PaymentsServices.createOrder Realizando baixa do estoque por produto e vinculando produtos ao pagamento. User: ' . Auth::user()->id);
            foreach ($itemsOrder as $product) {

                DB::table("products_stock")
                    ->where("id", "=", $product->stock_cod)
                    ->update(["stock" => $product->stock - $product->quantity]);

                DB::table("order_products")->insert([
                    "product_id" => $product->product_id,
                    "variable_cod" => $product->variable_cod,
                    "stock_cod" => $product->stock_cod,

                    "img_prod" => $product->imgProd,
                    "prod_name" => $product->prodName,
                    "variable_name" => $product->variableName,                        
                    "stock_name" => $product->stockName,
                    "phrase" => isset($product->phrase) ? $product->phrase : null,                        
                    
                    "quantity" => $product->quantity,
                    "total" => $product->total,
                    "subtotal" => $product->subtotal,
                    "price_unit" => $product->price_unit,
                    "discont" => $product->discont ?? 0,
                    "order_id" => $orderId
                ]);
            }

            DB::commit();

            Log::channel("information")->info('PaymentsServices.createOrder Pedido criado com sucesso!. User: ' . Auth::user()->id . " Pedido: " . $orderId);
            return $orderId;
        } catch (Exception $error) {
            Log::channel("exception")->error("PaymentsServices.createOrder: Ocorreu um erro na criação do pagamento: " . $error->getMessage());
            Log::channel("exception")->error($error);
            DB::rollBack();

            return null;
        }
    }

    function getPaymentStatus($orderId): OrderStatus
    {
        Log::channel("information")->info("PaymentsServices.getPaymentStatus - Consultando status do pagamento do pedido: " . $orderId);
        $statusPayment = OrderStatus::EM_ANALISE;

        try {
            DB::beginTransaction();

            $order = DB::table("orders")->where("id", "=", $orderId)->first();
            $responsePayments = $this->paymentsRepository->getPaymentByReferenceId($order->referency_id);

            if ($responsePayments != null) {
                $statusPayment = PaymentsUtils::getStatus($responsePayments->transaction->status);
                $orderBank = $this->paymentsRepository->getOrderPayment($order->transaction_code);

                if (
                    $statusPayment == OrderStatus::CANCELADO
                    && $order->status != OrderStatus::CANCELADO
                ) {
                    $this->refundOrder($orderId, $order->charge_id, (bool)$order->reversed);
                } else {
                    $updateOrder = [
                        "status" => $statusPayment,
                        "payment_status" => $responsePayments->transaction->status,
                        'update_at' => now()
                    ];
                }

                if ($orderBank != null) {
                    if ($orderBank->charges != null) {

                        $charge = $orderBank->charges[0];
                        $updateOrder["charge_id"] = $charge->id;

                        Log::channel("information")->info("PaymentsServices.getPaymentStatus - Atualizando log do pedido: " . $orderId);
                        DB::table("log_order")
                            ->updateOrInsert(
                                ["order_id" => $orderId],
                                [
                                    "code" => $charge->payment_response->code,
                                    "message" => $charge->payment_response->message
                                ]
                            );
                    }
                }

                Log::channel("information")->info("PaymentsServices.getPaymentStatus - Atualizando status do pedido: " . $orderId);
                DB::table("orders")
                    ->where("id", "=", $orderId)
                    ->update($updateOrder);

                DB::commit();
            }
        } catch (Exception $error) {
            DB::rollBack();
            throw $error;
        }

        Log::channel("information")->info("PaymentsServices.getPaymentStatus - Pedido " . $orderId . " com status " . $statusPayment->name);
        return $statusPayment;
    }

    function checkout(Request $request) {
        try {
            $jsonCart = $request->get("payload");
            $payload = (array)json_decode($jsonCart);

            $listProducts = $this->getProductsVariables($payload);

            $sizeOfProdcuts = sizeof($listProducts);

            if ($sizeOfProdcuts == 0) {
                Log::channel("exception")->error("PaymentsServices.checkout Não foram encontrados produtos para a compra: " . $jsonCart);
                return redirect("/")->withErrors(["error" => "Não foram encontrados produtos para a compra."]);
            } else {

                $productsView = [];
                $subTotal = 0;
                $total = 0;
                $totalDiscont = 0;

                foreach ($listProducts->all() as $product) {
                    $productCart = $this->getProductPayload($product->stock_cod, $payload);

                    if ($productCart == null) {
                        Log::channel("exception")->error("PaymentsServices.checkout O seguinte produto nao foi encontrado no payload: " . $product->prod_cod);
                        return redirect("/")->withErrors(["error" => "Não foram encontrados produtos para a compra."]);
                    } else {
                        $price = NumberUtils::calcPercent($product->price, $product->discont);

                        array_push($productsView, [
                            "prodCod" => $product->prod_cod,
                            "img_list" => $product->img_list != null ? explode(",", $product->img_list)[0] : "",
                            "name" => $product->name,                                                        
                            "price" => $product->price,
                            "stock" => $product->stock,
                            "discont" => $product->discont,                          
                            "variableName" => $product->variable_name,
                            "variableCod" => $product->variable_cod,
                            "stockName" => $product->stock_name,
                            "stockCod" => $product->stock_cod,

                            "totalPrice" => $price * $productCart->qtd,
                            "quantity" => $productCart->qtd,
                            "phrase" => isset($productCart->phrase) ? $productCart->phrase : null,
                        ]);

                        $subTotal += $product->price * $productCart->qtd;
                        $totalDiscont += $productCart->qtd * NumberUtils::calcDiscont($product->price, $product->discont);
                    }
                }

                $total += $subTotal - $totalDiscont;

                $summary = (object)[
                    "subTotal" => $subTotal,
                    "total" => $total,
                    "sizeOfProdcuts" => $sizeOfProdcuts,
                    "totalDiscont" => $totalDiscont,
                    "payload" => $payload,
                    "publicKey" => $this->getPublicKey()
                ];

                $user = Auth::user();
                $shipping = $this->shippingService->getShipping($user->cep, $productsView);

                return view("checkout", [
                    "products" => $productsView,
                    "summary" => $summary,
                    "user" => $user,
                    "shipping" => $shipping,
                    "instalmentsConfig" => Cache::get("general")["config"]->instalments_not_fees
                ]);
            }
        } catch (Exception $e) {
            Log::error("PaymentsServices.checkout: " . $e);
            Log::channel("exception")->error("PaymentsServices.checkout: " . $e->getMessage());

            throw $e;
        }
    }

    //FUNCOES PRIVADAS

    private function insertShipping(Request $request, $payloadService): int
    {
        Log::channel("information")->info('PaymentsServices.savePixQrCode Realizando inserção de dados do Frete. User: ' . Auth::user()->id);
        return DB::table("order_shipping")
            ->insertGetId([
                "total_shipping" => $payloadService->ShippingPrice,
                "date_previous" => now()->addDays($payloadService->DeliveryTime),
                "type_shipping" => $payloadService->ServiceDescription,
                "service_code" => $payloadService->ServiceCode,
                "carrier" => $payloadService->Carrier,

                "address" => $request->get("address"),
                "addressNumber" => $request->get("addressNumber"),
                "complement" => $request->get("complement"),
                "neighborhood" => $request->get("neighborhood"),
                "city" => $request->get("city"),
                "state" => $request->get("state"),
                "cep" => $request->get("cep")
            ]);
    }

    private function saveLogPaymentCard($typePayment, $orderId, $dataPayment)
    {
        if ($typePayment == "CARD") {
            $charge = $dataPayment->charges[0];

            Log::channel("information")->info("PaymentsServices.saveLogPaymentCard Atualizando log do pedido: " . $orderId);
            DB::table("log_order")
                ->insert([
                    "code" => $charge->payment_response->code,
                    "message" => $charge->payment_response->message,
                    "order_id" => $orderId
                ]);
        }
    }

    private function savePixQrCode($typePayment, $orderId, $dataPayment)
    {
        if ($typePayment == "PIX") {
            $qrCode = $dataPayment->qr_codes[0];
            $qrCodeLink = null;

            foreach ($qrCode->links as $link) {
                if (str_contains($link->rel, "PNG")) {
                    $qrCodeLink = $link->href;
                    break;
                }
            }

            Log::channel("information")->info('PaymentsServices.savePixQrCode Realizando inserção de dados do PIX. User: ' . Auth::user()->id);
            DB::table("order_pix")->insert([
                "expiration_date" => Carbon::parse($qrCode->expiration_date),
                "text" => $qrCode->text,
                "qr_code" => $qrCodeLink,
                "order_id" => $orderId
            ]);
        }
    }

    private function createPayload(Request $request, $items, $total, $type)
    {
        try {
            $referenceId = Str::random(64);

            if ($type == null) {
                throw new Exception("É necessário informar o tipo do pagamento.");
            }

            $payload = (object)[
                "reference_id" => $referenceId,
                "customer" => [
                    "name" => Auth::user()->name,
                    "email" => Auth::user()->email,
                    "tax_id" => Auth::user()->cpf,
                    "phones" => [
                        [
                            "country" => "55",
                            "area" => Auth::user()->ddd_phone,
                            "number" => Auth::user()->phone,
                            "type" => "MOBILE"
                        ]
                    ]
                ],
                "items" => $items,
                "notification_urls" => [
                    route('notification.callback')                    
                ],
                "shipping" => [
                    "address" => [
                        "street" => $request->get("address"),
                        "number" => $request->get("addressNumber"),
                        "complement" => $request->get("complement"),
                        "locality" => $request->get("neighborhood"),
                        "city" => $request->get("city"),
                        "region_code" => $request->get("state"),
                        "country" => "BRA",
                        "postal_code" => $request->get("cep")
                    ]
                ]
            ];

            switch ($type) {
                case "PIX":
                    $payload->qr_codes = [
                        [
                            "amount" => [
                                "value" => $this->formatPricePayload($total)
                            ],
                            "expiration_date" => now()->addMinutes(5)
                        ]
                    ];
                    break;
                case "CARD":
                    $instalments = intval($request->get("installments"));
                    $instalmentsFees = $this->getInstallmentsFees($total, $request->get("creditBin"));

                    $payloadAmount = $instalmentsFees[$instalments - 1]["amount"];

                    $payload->charges = [
                        [
                            "reference_id" => $referenceId,
                            "description" => "PAGAMENTO DE SEMIJOIAS DA EMPRESA VALVIT SEMIJOIAS",
                            "amount" => $payloadAmount,
                            "payment_method" => [
                                "type" => "CREDIT_CARD",
                                "installments" => $instalments,
                                "soft_descriptor" => "VALVIT_EC",
                                "capture" => true,
                                "card" => [
                                    "encrypted" => $request->get("encryptedCard"),
                                    "security_code" => $request->get("cardHolderCvv"),
                                    "holder" => [
                                        "name" => $request->get("cardHolder"),
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;
            }

            return $payload;
        } catch (Exception $error) {
            Log::channel("exception")->error($error->getMessage());
            throw $error;
        }
    }

    private function formatPricePayload($value)
    {
        $value = number_format($value, 2);
        $valueExplode = explode(".", str(floatval($value)));
        if (sizeof($valueExplode) == 2) {
            $valueFloat = str_pad($valueExplode[1], 2, "0", STR_PAD_RIGHT);
        } else {
            $valueFloat = str_pad("0", 2, "0", STR_PAD_RIGHT);
        }

        return $valueExplode[0] . $valueFloat;
    }

    private function getProductPayload(string $stockCod, array $listProduct)
    {
        foreach ($listProduct as $product) {
            if ($product->stockCod == $stockCod) {
                return $product;
            }
        }
    }

    private function getProductsVariables($payload):Collection
    {
        $stockListCod = [];
        $variableListCod = [];

        if (sizeof($payload) > 0) {
            $stockListCod = array_map((function ($data) {
                return $data->stockCod;
            }), $payload);

            $variableListCod = array_map((function ($data) {
                return $data->variableCod;
            }), $payload);
        } else {
            $message = "PaymentsServices.checkout Não foram encontrados produtos para a compra: " . json_encode($payload);
            Log::channel("exception")->error($message);
            throw new Exception($message);
        }

        return DB::table("products as P")
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")
            ->whereIn("PV.id", $variableListCod)
            ->whereIn("PS.id", $stockListCod)
            ->where("PS.stock", ">", 0)
            ->get();
    }    
}
