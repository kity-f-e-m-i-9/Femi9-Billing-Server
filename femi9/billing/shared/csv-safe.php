<?php
/**
 * CSV formula-injection guard: drop-in for fputcsv(). A cell that a spreadsheet
 * would treat as a formula (starts with = @ TAB CR, or + / - followed by a
 * non-number) gets a leading apostrophe so it opens as plain text.
 */
if (!function_exists('femi_fputcsv')) {
    function femi_csv_cell($v)
    {
        if (!is_string($v) || $v === '') { return $v; }
        $c = $v[0];
        if ($c === '=' || $c === '@' || $c === "\t" || $c === "\r"
            || (($c === '+' || $c === '-') && isset($v[1]) && !preg_match('/[0-9.\s]/', $v[1]))) {
            return "'" . $v;
        }
        return $v;
    }

    function femi_fputcsv($handle, array $fields, string $separator = ',', string $enclosure = '"', string $escape = '\\', string $eol = "\n")
    {
        return fputcsv($handle, array_map('femi_csv_cell', $fields), $separator, $enclosure, $escape, $eol);
    }
}
