<?php

namespace Moontec\Models;

class PaymentChageModel
{
    public String $type;
    public int $installments;
    public String $soft_descriptor;
    public bool $capture;
    public CreditCardModel $card;
}