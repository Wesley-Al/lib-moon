<?php

namespace Moontec\Services;

use Moontec\Models\PhoneModel;
use Moontec\Models\ShippingDataModel;

class ClientView
{
    public String $name;
    public String $email;
    public String $cpf;
    public array $phones;
    public ShippingDataModel $address;
}