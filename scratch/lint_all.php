<?php
$dir = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$has_error = false;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        $output = [];
        $return_var = 0;
        exec('C:\xampp\php\php.exe -l ' . escapeshellarg($path), $output, $return_var);
        if ($return_var !== 0) {
            echo "ERROR in $path:\n" . implode("\n", $output) . "\n";
            $has_error = true;
        }
    }
}

if (!$has_error) {
    echo "All PHP files in fmb-ecom-store passed syntax check!\n";
}
