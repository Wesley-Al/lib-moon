<?php

namespace Moontec\Models;

class ChargeModel 
{
    public String $reference_id;
    public String $description;
    public AmountDataModel $amount;
    public PaymentChageModel $payment_method;
}