<?php

namespace Moontec\Services;

use Moontec\Repository\NotificationRepository;
use Moontec\Repository\PaymentsRepository;
use Moontec\Utils\PaymentsUtils;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(protected NotificationRepository $notificationRepository, protected PaymentsRepository $paymentsRepository)
    {
    }

    public function notificationProccess($notificationCode)
    {
        try {
            DB::beginTransaction();

            Log::channel("information")->info("Iniciando processamento de notificação de transação: " . $notificationCode);
            $notification = $this->notificationRepository->getNotification($notificationCode);

            $order = DB::table("orders")
                ->where("referency_id", "=", $notification->reference)->first();

            $orderStatus = PaymentsUtils::getStatus($notification->status);
            $orderBank = $this->paymentsRepository->getOrderPayment($order->transaction_code);;

            $updateOrder = [
                "status" => $orderStatus,
                "payment_status" => $notification->status
            ];

            if ($orderBank != null) {
                if ($orderBank->charges != null) {
                    $charge = $orderBank->charges[0];
                    $updateOrder["charge_id"] = $charge->id;

                    Log::channel("information")->info("NotificationService.notificationProccess - Atualizando log do pedido: " . $order->id);
                    DB::table("log_order")
                        ->updateOrInsert(
                            ["order_id" => $order->id],
                            [
                                "code" => $charge->payment_response->code,
                                "message" => $charge->payment_response->message
                            ]
                        );
                }
            }

            Log::channel("information")->info("Atualizando status no Banco de Dados: " . $notification->reference);
            DB::table("orders")
                ->where("id", "=", $notification->reference)
                ->update($updateOrder);

            DB::commit();
        } catch (Exception $error) {
            Log::channel("exception")->error("NotificationService.notificationProccess: Ocorreu um erro no processamento: " . $error->getMessage());
            DB::rollBack();
        }
    }
}
