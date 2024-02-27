<?php

namespace Moontec\Enums;

enum PaymentStatus: int
{
        /*O comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento. */
    case AGUARDANDO_PAGAMENTO = 1;

        /*O comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação. */
    case EM_ANALISE = 2;

        /*A transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação da instituição financeira responsável pelo 
    processamento. Quando uma transação tem seu status alterado para Paga, isso significa que você já pode liberar o 
    produto vendido ou prestar o serviço contratado. Porém, note que o valor da transação pode ainda não estar
    disponível para retirada de sua conta, pois o PagSeguro pode esperar o fim do prazo de liberação da transação. */
    case PAGA = 3;

        /*A transação foi paga e chegou ao final de seu prazo de liberação sem ter sido retornada e sem que haja nenhuma
     disputa aberta. Este status indica que o valor da transação está disponível para saque. */
    case DISPONIVEL = 4;

        /*O comprador, dentro do prazo de liberação da transação, abriu uma disputa. A disputa é um processo iniciado pelo
     comprador para indicar que não recebeu o produto ou serviço adquirido, ou que o mesmo foi entregue com problemas.
     Este é um mecanismo de segurança oferecido pelo PagSeguro. A equipe do PagSeguro é responsável por mediar a
     resolução de todas as disputas, quando solicitado pelo comprador.*/
    case EM_DISPUTA = 5;

        /*O *valor da transação foi devolvido para o comprador. Se você não possui mais o produto vendido em estoque, ou 
    não pode por alguma razão prestar o serviço contratado, você pode devolver o valor da transação para o comprador. 
    Esta também é a ação tomada quando uma disputa é resolvida em favor do comprador. Transações neste status não 
    afetam o seu saldo no PagSeguro, pois não são nem um crédito e nem um débito.*/
    case DEVOLVIDA = 6;

        /*A transação foi cancelada sem ter sido finalizada. Quando o comprador opta por pagar com débito online ou
     boleto bancário e não finaliza o pagamento, a transação assume este status. Isso também ocorre quando o 
     comprador escolhe pagar com um cartão de crédito e o pagamento não é aprovado pelo PagSeguro ou pela
    operadora. */
    case CANCELADA = 7;

        /*A valor da transação foi devolvido para o comprador. */
    case DEBITADO = 8;

        /*O comprador abriu uma solicitação de chargeback junto à operadora do cartão de crédito.*/
    case RETENCAO_TEMPORARIA = 9;
}