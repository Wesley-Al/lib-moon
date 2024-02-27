<?php

namespace Moontec\Enums;

enum OrderStatus: int
{
    case EM_PREPARACAO = 1;
    case PENDENTE_PAGAMENTO = 2;
    case PENDENTE_ENVIO = 3;
    case ENVIADO = 4;
    case CONCLUIDO = 5;
    case CANCELADO = 6;
    case EM_ANALISE = 7;
}