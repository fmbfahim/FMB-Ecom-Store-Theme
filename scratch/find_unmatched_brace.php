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
        } elseif ($t === '}') {
            $braces--;
            if ($braces < 0) {
                echo "UNMATCHED CLOSING BRACE at line $line_no!\n";
                // Print surrounding lines
                $start = max(0, $line_no - 10);
                $end = min(count($lines), $line_no + 10);
                for ($i = $start; $i < $end; $i++) {
                    echo ($i + 1) . ": " . $lines[$i];
                }
                break;
            }
        }
    }
}
echo "Final braces count: $braces\n";
