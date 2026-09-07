<?php
$dir2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc';
$dirTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc';

function list_php_files($dir) {
    $res = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isDir() || $f->getExtension() !== 'php') continue;
        $res[] = str_replace($dir . '/', '', str_replace('\\', '/', $f->getPathname()));
    }
    return $res;
}

$files2027 = list_php_files($dir2027);
$filesTarget = list_php_files($dirTarget);

echo "Files in 2027 inc but not in Target:\n";
$diff1 = array_diff($files2027, $filesTarget);
if (empty($diff1)) echo "  (None)\n";
foreach ($diff1 as $f) echo "  - $f\n";

echo "Files in Target inc but not in 2027:\n";
$diff2 = array_diff($filesTarget, $files2027);
foreach ($diff2 as $f) echo "  - $f\n";
