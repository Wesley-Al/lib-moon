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
    public function __construct(protected PaymentsRepository $paymentsRepository)
    {
    }

    public function updatePaymentAwaitPay()
    {
        try {
            Log::channel("exception")->info("CronService.updatePaymentAwaitPay Iniciando cron para atualização dos pedidos aguardando pagamento");
            DB::beginTransaction();

            $updateAt = now();
            $dateMin = DB::table("orders")->where("status", "=", OrderStatus::PENDENTE_PAGAMENTO)->min("create_at");

            $orders = DB::table("orders as O")
                ->where("status", "=", OrderStatus::PENDENTE_PAGAMENTO)->get();

            $ordersBank = $this->paymentsRepository->getListPayments($dateMin, now());

            Log::channel("exception")->info("CronService.updatePaymentAwaitPay Foram encontrados " . sizeof($orders) . " pedidos aguardando pagamento");

            foreach ($orders as $order) {
                $subArray = [];
                $orderStatus = null;
                $paymentStatus = null;

                $data = array_filter($ordersBank->transactions->transaction, function ($dataBank) use ($order) {
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

                DB::table("orders")
                    ->where("id", "=", $order->id)
                    ->update([
                        "status" => $orderStatus,
                        "payment_status" => $paymentStatus,
                        "update_at" => $updateAt
                    ]);
            }

            DB::commit();
            Log::channel("exception")->info("CronService.updatePaymentAwaitPay Finalizando cron com Sucesso!");
        } catch (Exception $error) {
            DB::rollBack();
            Log::channel("exception")->info("CronService.updatePaymentAwaitPay Ocorreu um erro durante o processo de atualizaco dos pagamentos pendentes: " . $error->getMessage());
        }
    }
}
