<?php
$src = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc';
$dst = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
foreach ($it as $f) {
    if ($f->isDir()) continue;
    $rel = str_replace($src . '/', '', str_replace('\\', '/', $f->getPathname()));
    $target_file = "$dst/$rel";
    if (!file_exists($target_file)) {
        $target_dir = dirname($target_file);
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        copy($f->getPathname(), $target_file);
        echo "Copied missing file to 2027: $rel\n";
    }
}
echo "All missing inc files copied to 2027!\n";
