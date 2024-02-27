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
        return $price - $price * NumberUtils::formatCalcPercent($percent);
    }

    public static function calcDiscont($price, $percent)
    {        
        return $price * NumberUtils::formatCalcPercent($percent);
    }

    public static function formatCalcPercent($percent)
    {
        $valuePercent = str_pad(str_replace(".", "", strval($percent)), 2, "0", STR_PAD_LEFT);
        return floatval('0.' . $valuePercent);
    }
}