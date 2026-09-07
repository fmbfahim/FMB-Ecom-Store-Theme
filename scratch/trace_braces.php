<?php
$lines = file('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php');
$code = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php');
$tokens = token_get_all($code);
$braces = 0;
$line_no = 1;

foreach ($tokens as $t) {
    if (is_array($t)) {
        $line_no = $t[2];
    } elseif (is_string($t)) {
        if ($t === '{') {
            $braces++;
            echo "Line $line_no: { (count: $braces)\n";
        } elseif ($t === '}') {
            $braces--;
            echo "Line $line_no: } (count: $braces)\n";
        }
    }
    if ($line_no > 80) break;
}
