<?php
$dir2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir2027));
foreach ($it as $f) {
    if ($f->isDir() || $f->getExtension() !== 'php') continue;
    $content = file_get_contents($f->getPathname());
    if (strpos($content, 'add_menu_page') !== false || strpos($content, 'add_submenu_page') !== false) {
        echo "File: " . str_replace($dir2027 . '/', '', str_replace('\\', '/', $f->getPathname())) . "\n";
        preg_match_all('/(add_menu_page|add_submenu_page)\s*\(([^;]+)\);/s', $content, $m);
        foreach ($m[0] as $match) {
            // compact lines
            $clean = preg_replace('/\s+/', ' ', trim($match));
            echo "  " . substr($clean, 0, 120) . "\n";
        }
    }
}
