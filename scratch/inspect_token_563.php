<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$tokens = token_get_all(file_get_contents($file));

$depth = 0;
for ($i = 0; $i < count($tokens); $i++) {
    $t = $tokens[$i];
    $name = is_array($t) ? token_name($t[0]) : $t;
    $text = is_array($t) ? $t[1] : $t;
    $line = is_array($t) ? $t[2] : '-';
    
    if ($text === '{') {
        $depth++;
        // find context
        $ctx = '';
        for ($j = max(0, $i - 10); $j < $i; $j++) {
            $ctx .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
        }
        echo "Line $line: OPEN brace (depth -> $depth) after: " . trim(preg_replace('/\s+/', ' ', $ctx)) . "\n";
    } elseif ($text === '}') {
        $depth--;
        echo "Line $line: CLOSE brace (depth -> $depth)\n";
    }
    
    if ($depth < 0) {
        echo "ALERT: Below zero at line $line (token $i)!\n";
        break;
    }
}
