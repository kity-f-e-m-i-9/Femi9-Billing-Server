<?php
function RemoveSpecialChar($str) {
      // Using str_replace() function 
      // to replace the word 
      $res = str_replace( array( '<', '>','^','$','?' ), ' ', $str);
      // Returning the result 
      return $res;
      }
?>