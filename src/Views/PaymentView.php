<?php

namespace Moontec\Views;

use Moontec\Services\ClientView;
use Moontec\Services\OrderView;
use Moontec\Services\ProductView;

class PaymentView
{
    public String $notification_url;
    public ClientView $client;
    public OrderView $order;
    public array $items = array();
}