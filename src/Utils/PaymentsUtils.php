<?php

namespace Moontec\Utils;

use Moontec\Enums\OrderStatus;
use Moontec\Enums\PaymentStatus;

class PaymentsUtils extends Utils
{
    public static function getStatus(int $paymentCode): OrderStatus
    {
        $orderStatus = null;        
        $paymentStatus = PaymentStatus::from($paymentCode);
        
        if ($paymentStatus == PaymentStatus::DISPONIVEL || $paymentStatus == PaymentStatus::PAGA) {
            $orderStatus = OrderStatus::PENDENTE_ENVIO;
        } else if (
            $paymentStatus == PaymentStatus::EM_ANALISE || $paymentStatus == PaymentStatus::EM_DISPUTA
            || $paymentStatus == PaymentStatus::RETENCAO_TEMPORARIA
        ) {
            $orderStatus = OrderStatus::EM_ANALISE;
        } else if ($paymentStatus == PaymentStatus::AGUARDANDO_PAGAMENTO) {
            $orderStatus = OrderStatus::PENDENTE_PAGAMENTO;
        } else {
            $orderStatus = OrderStatus::CANCELADO;
        }

        return $orderStatus;
    }
}