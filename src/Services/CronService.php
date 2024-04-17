<?php

namespace Moontec\Services;

use Moontec\Utils\PaymentsUtils;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moontec\Enums\OrderStatus;
use Moontec\Enums\PaymentStatus;
use Moontec\Repository\PaymentsRepository;

class CronService
{
    public function __construct(protected PaymentsRepository $paymentsRepository, protected PaymentsServices $paymentsServices)
    {
    }

    public function updatePaymentAwaitPay()
    {
        try {
            Log::channel("cronjob")->info("CronService.updatePaymentAwaitPay Iniciando CRON para atualização dos pedidos AGUARDANDO PAGAMENTO");
            DB::beginTransaction();

            $updateAt = now();
            $dateMin = DB::table("orders")->where("status", "=", OrderStatus::PENDENTE_PAGAMENTO)->min("create_at");

            $orders = DB::table("orders as O")
                ->where("status", "=", OrderStatus::PENDENTE_PAGAMENTO)->get();

            $ordersBank = $this->paymentsRepository->getListPayments($dateMin, now());            

            Log::channel("cronjob")->info("CronService.updatePaymentAwaitPay Foram encontrados " . sizeof($orders) . " pedidos AGUARDANDO PAGAMENTO");

            foreach ($orders as $order) {
                $subArray = [];                
                $orderStatus = null;
                $paymentStatus = null;

                $data = array_filter($ordersBank->transactions, function ($dataBank) use ($order) {
                    return $order->referency_id == $dataBank->reference;
                });

                if (sizeof($data) != 0) {
                    array_push($subArray, array_shift($data));

                    $orderStatus = PaymentsUtils::getStatus($subArray[0]->status);
                    $paymentStatus = PaymentStatus::from($subArray[0]->status);
                } else {
                    $orderStatus = OrderStatus::CANCELADO;
                    $paymentStatus = PaymentStatus::CANCELADA;
                }

                if($orderStatus == OrderStatus::CANCELADO) {
                    $this->paymentsServices->refundOrder($order->id, $order->charge_id, $order->reversed);
                }else {
                    DB::table("orders")
                    ->where("id", "=", $order->id)
                    ->update([
                        "status" => $orderStatus,
                        "payment_status" => $paymentStatus,
                        "update_at" => $updateAt
                    ]);
                }                
            }

            DB::commit();
            Log::channel("cronjob")->info("CronService.updatePaymentAwaitPay Finalizando CRON com Sucesso!");
        } catch (Exception $error) {
            DB::rollBack();
            Log::channel("cronjob")->info("CronService.updatePaymentAwaitPay Ocorreu um erro durante o processo de atualização dos PAGAMENTOS PENDENTES: " . $error->getMessage());
        }
    }
}
