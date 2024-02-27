<?php

namespace Moontec\Models;

class PaymentModel
{
    public String $reference_id;
    public CustomerModel $customer;
    public array $items = array();
    public array $notification_urls;
    public ShippingModel $shipping;

    public array $qr_codes = array();
    public array $charges = array();
}
