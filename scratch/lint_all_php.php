<?php
$dir = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';

$errors = [];
$checked = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($it as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() !== 'php') continue;
    
    $path = $file->getPathname();
    if (strpos($path, 'scratch') !== false) continue;
    
    $output = [];
    $ret = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $ret);
    $checked++;
    
    if ($ret !== 0) {
        $errors[$path] = implode("\n", $output);
    }
}

echo "Total PHP files checked: $checked\n";
if (empty($errors)) {
    echo "SUCCESS: All $checked PHP files in fmb-ecom-store passed syntax linting with 0 errors!\n";
} else {
    echo "ERRORS detected in " . count($errors) . " files:\n";
    foreach ($errors as $p => $err) {
        echo "- $p:\n$err\n\n";
    }
}
