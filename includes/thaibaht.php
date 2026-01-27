<?php
if (!function_exists('ThaiBahtConversion')) {
    function ThaiBahtConversion($amount_number)
    {
        $amount_number = number_format($amount_number, 2, ".", "");
        $pt = strpos($amount_number, ".");
        $number = $fract = "";
        if ($pt === false) {
            $number = $amount_number;
        } else {
            $number = substr($amount_number, 0, $pt);
            $fract = substr($amount_number, $pt + 1);
        }

        $ret = "";
        $baht = ReadNumber($number);
        if ($baht != "") {
            $ret .= $baht . "บาท";
        }

        $satang = ReadNumber($fract);
        if ($satang != "") {
            $ret .= $satang . "สตางค์";
        } else {
            $ret .= "ถ้วน";
        }
        return $ret;
    }
}

if (!function_exists('ReadNumber')) {
    function ReadNumber($number)
    {
        $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
        $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
        $number = $number + 0;
        $ret = "";
        if ($number == 0)
            return "";
        $len = strlen($number);
        for ($i = 0; $i < $len; $i++) {
            $n = substr($number, $i, 1);
            if ($n != 0) {
                if ($i == ($len - 1) && $n == 1)
                    $ret .= "เอ็ด";
                else if ($i == ($len - 2) && $n == 2)
                    $ret .= "ยี่";
                else if ($i == ($len - 2) && $n == 1)
                    $ret .= "";
                else
                    $ret .= $number_call[$n];
                $ret .= $position_call[$i + 6 - $len];
            }
        }
        return $ret;
    }
}
?>