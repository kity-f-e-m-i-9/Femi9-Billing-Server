<?php
if (!function_exists('inr_format')) {
    // Formats a number using the Indian numbering system (lakh/crore grouping),
    // e.g. 1234567.89 -> "12,34,567.89". Signature mirrors number_format()'s
    // (value, decimals) usage so display call sites can be swapped in directly.
    function inr_format($number, $decimals = 2) {
        $number = is_numeric($number) ? (float)$number : 0.0;
        $negative = $number < 0;
        $number = abs($number);

        $fixed = number_format($number, $decimals, '.', '');
        $parts = explode('.', $fixed);
        $integer = $parts[0];
        $decimalPart = isset($parts[1]) ? '.' . $parts[1] : '';

        if (strlen($integer) > 3) {
            $lastThree = substr($integer, -3);
            $remaining = substr($integer, 0, -3);
            $remaining = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining);
            $integer = $remaining . ',' . $lastThree;
        }

        return ($negative ? '-' : '') . $integer . $decimalPart;
    }
}

if (!function_exists('number_to_words_inr')) {
    // Spells out the integer part of a rupee amount in words using the
    // Indian numbering system (hundred/thousand/lakh/crore), e.g.
    // 123456.78 -> "one lakh twenty three thousand four hundred and
    // fifty six". Extracted from the identical amount-in-words /
    // tax-amount-in-words logic duplicated across the invoice print pages,
    // so the on-screen Print page and the shared-PDF endpoint stay in sync.
    function number_to_words_inr($amount) {
        $no       = floor((float)$amount);
        $digits_1 = strlen((string)$no);
        $i        = 0;
        $str      = [];
        $words    = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty','50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
        $digits   = ['','hundred','thousand','lakh','crore'];
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number2 = floor($no % $divider);
            $no      = floor($no / $divider);
            $i      += ($divider == 10) ? 1 : 2;
            if ($number2) {
                $plural  = (($counter = count($str)) && $number2 > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[]   = ($number2 < 21) ? $words[$number2] . " " . $digits[$counter] . $plural . " " . $hundred
                    : $words[floor($number2/10)*10] . " " . $words[$number2%10] . " " . $digits[$counter] . $plural . " " . $hundred;
            } else { $str[] = null; }
        }
        $str = array_reverse($str);
        return implode('', $str);
    }
}
