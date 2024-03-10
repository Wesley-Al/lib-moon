<?php

namespace Moontec\Utils;

class NumberUtils extends Utils
{
    public static function formatCurrency($value, $dollarSign = true, $percent = false)
    {
        $price = null;        

        if($percent && strlen($value) <= 2) {
            $price = strval($value);
        }else {
            $price = number_format($value, 2, ',', '.');
        }       

        return  $dollarSign ? "R$ " . $price : $price;
    }

    public static function calcPercent($price, $percent)
    {   
        return $price - NumberUtils::calcDiscont($price, $percent);
    }

    public static function calcDiscont($price, $percent)
    {        
        return $percent/100 * $price;
    }   
}