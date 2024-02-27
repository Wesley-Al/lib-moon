<?php

namespace Moontec\Models;

class CreditCardModel 
{
    public String $encrypted;
    public String $security_code;
    public HolderCardModel $holder;
}