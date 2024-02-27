<?php

namespace Moontec\Services;

use Moontec\Models\HolderCardModel;

class CreditCardView
{    
    public String $description;
    public String $soft_descriptor;
    public String $encrypted;
    public String $security_code;
    public HolderCardModel $holder;
    
}