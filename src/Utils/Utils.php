<?php

namespace Moontec\Utils;

use Illuminate\Support\Facades\Cache;

class Utils
{
    public static function isNullOrEmpty($value): bool
    {
        return $value == null || $value == "";
    }

    public static function getListCategory()
    {        
        return Cache::get("general")["categorys"];
    }

    public static function mask($val, $mask)
    {
        $maskared = '';
        $k = 0;
        for ($i = 0; $i <= strlen($mask) - 1; ++$i) {
            if ($mask[$i] == '#') {
                if (isset($val[$k])) {
                    $maskared .= $val[$k++];
                }
            } else {
                if (isset($mask[$i])) {
                    $maskared .= $mask[$i];
                }
            }
        }

        return $maskared;
    }
}
