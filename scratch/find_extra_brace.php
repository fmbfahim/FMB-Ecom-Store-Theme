<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$tokens = token_get_all(file_get_contents($file));

$depth = 0;
$line = 1;

foreach ($tokens as $index => $token) {
    if (is_array($token)) {
        $line = $token[2];
        continue;
    }
    
    if ($token === '{') {
        $depth++;
    } elseif ($token === '}') {
        $depth--;
        if ($depth < 0) {
            echo "EXTRA/UNEXPECTED '}' at line $line (token $index)!\n";
            break;
        }
    }
}
