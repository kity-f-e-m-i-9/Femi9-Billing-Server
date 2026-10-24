<?php
/**
 * Parameterised replacement for mysqli_query() on interpolated SQL.
 *
 *   db_q($conn, "select * from t where id=?", [$id])
 *
 * - INSERT / UPDATE / DELETE / REPLACE: real server-side prepared statements
 *   (parameters never become part of the SQL text). Returns true/false.
 * - SELECT: parameters are bound client-side - each value is escaped with
 *   mysqli_real_escape_string() and quoted - and run through the normal text
 *   protocol. A server-side prepared SELECT would hand back FLOAT columns as
 *   binary floats (0.1 -> 0.10000000149...), silently changing money
 *   displayed by pages that echo raw columns (amount/total/rwpoints are FLOAT
 *   in this schema). Text-protocol results keep every column type identical.
 * - Parameters are always treated as strings; null / array become ''
 *   (what '$var' interpolation produced). The SQL template must contain a
 *   '?' only where a parameter goes.
 * - mysqli_insert_id() / mysqli_affected_rows() on the connection still work.
 */
if (!function_exists('db_q')) {
    function db_q($conn, $sql, array $params = [])
    {
        if (!$params) {
            return mysqli_query($conn, $sql);
        }
        $vals = [];
        foreach ($params as $p) {
            $vals[] = is_scalar($p) ? (string)$p : '';
        }

        if (preg_match('/^\s*(select|show|describe|with)\b/i', $sql)) {
            $parts = explode('?', $sql);
            if (count($parts) - 1 !== count($vals)) {
                return false;
            }
            $built = array_shift($parts);
            foreach ($parts as $i => $part) {
                $built .= "'" . mysqli_real_escape_string($conn, $vals[$i]) . "'" . $part;
            }
            return mysqli_query($conn, $built);
        }

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($vals)), ...$vals);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('db_esc')) {
    /**
     * Escapes a value for use inside a single-quoted SQL literal built by
     * string interpolation. Used where a statement could not be converted to
     * db_q() placeholders. Prefer db_q() for new code.
     */
    function db_esc($value)
    {
        $v = is_scalar($value) ? (string)$value : '';
        if (isset($GLOBALS['db_conn']) && $GLOBALS['db_conn'] instanceof mysqli) {
            return mysqli_real_escape_string($GLOBALS['db_conn'], $v);
        }
        return addslashes($v);
    }
}
