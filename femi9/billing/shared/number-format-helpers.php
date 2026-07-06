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
