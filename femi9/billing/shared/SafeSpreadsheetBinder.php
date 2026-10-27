<?php
/**
 * Spreadsheet formula-injection guard for every .xlsx export.
 *
 * PhpSpreadsheet turns any string that starts with "=" into a live formula.
 * Exports print user-entered text (names, remarks, addresses), so a value like
 * =HYPERLINK("http://evil",...) or =cmd|' /C calc'!A0 would run when someone
 * opens the file. This binder writes such values as plain TEXT instead.
 *
 * The exports' own totals rows (=SUM(G4:G20), simple IF/ROUND, ...) are kept:
 * a formula is only allowed if every function it calls is on a short safe list
 * and it has no external-reference / DDE characters.
 *
 * Install once per export, before creating the Spreadsheet:
 *   require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
 *   \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());
 */
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

if (!class_exists('FemiSafeValueBinder')) {
    class FemiSafeValueBinder extends DefaultValueBinder
    {
        private const SAFE_FUNCS = ['SUM', 'COUNT', 'COUNTA', 'AVERAGE', 'MIN', 'MAX', 'ROUND', 'IF', 'IFERROR', 'ABS', 'SUBTOTAL'];

        private static function isSafeFormula(string $v): bool
        {
            if (strpbrk($v, "|![]\\@\r\n\t") !== false) { return false; }
            if (preg_match_all('/([A-Za-z_][A-Za-z0-9_.]*)\s*\(/', $v, $m)) {
                foreach ($m[1] as $fn) {
                    if (!in_array(strtoupper($fn), self::SAFE_FUNCS, true)) { return false; }
                }
            }
            return true;
        }

        public function bindValue(Cell $cell, mixed $value = null): bool
        {
            if (is_string($value) && $value !== '') {
                $first = $value[0];
                $looksActive = ($first === '=' || $first === '@' || $first === "\t" || $first === "\r")
                    || (($first === '+' || $first === '-') && isset($value[1]) && !preg_match('/[0-9.\s]/', $value[1]));
                if ($looksActive && !($first === '=' && self::isSafeFormula($value))) {
                    $cell->setValueExplicit($value, DataType::TYPE_STRING);
                    return true;
                }
            }
            return parent::bindValue($cell, $value);
        }
    }
}
