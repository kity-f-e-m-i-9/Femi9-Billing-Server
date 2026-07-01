<?php
function RemoveSpecialChar($str) {
    $res = str_replace(array("'", '"', ';', '<', '>', '{', '}', '(', ')', '^', '[', ']', '*', '$', '='), ' ', $str);
    return $res;
}
?>
