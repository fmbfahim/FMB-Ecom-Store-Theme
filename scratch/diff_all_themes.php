<?php
$dir_active = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';
$dir_2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';

function scan_dir_recursive($dir) {
    $result = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.git' || $item === 'scratch' || $item === 'node_modules') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $result = array_merge($result, scan_dir_recursive($path));
        } else {
            $result[] = $path;
        }
    }
    return $result;
}

$active_files = scan_dir_recursive($dir_active);
$z2027_files = scan_dir_recursive($dir_2027);

$rel_active = array_map(function($f) use ($dir_active) { return substr($f, strlen($dir_active) + 1); }, $active_files);
$rel_2027 = array_map(function($f) use ($dir_2027) { return substr($f, strlen($dir_2027) + 1); }, $z2027_files);

$all_rel = array_unique(array_merge($rel_active, $rel_2027));
sort($all_rel);

echo "=== ALL DIFFERENCES (2027 vs Active) ===\n";
foreach ($all_rel as $rel) {
    $p1 = "$dir_active/$rel";
    $p2 = "$dir_2027/$rel";
    if (!file_exists($p1)) {
        echo "[ONLY IN 2027] $rel (" . filesize($p2) . " bytes)\n";
    } elseif (!file_exists($p2)) {
        echo "[ONLY IN ACTIVE] $rel (" . filesize($p1) . " bytes)\n";
    } else {
        if (md5_file($p1) !== md5_file($p2)) {
            echo "[DIFFERENT] $rel (Active: " . filesize($p1) . " vs 2027: " . filesize($p2) . ")\n";
        }
    }
}
