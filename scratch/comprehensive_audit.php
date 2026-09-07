<?php
$dir2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';
$dirTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';

function scan_all($dir, $base = '') {
    $files = [];
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..' || $item === '.git' || $item === 'scratch') continue;
        $path = $dir . '/' . $item;
        $rel = $base ? $base . '/' . $item : $item;
        if (is_dir($path)) {
            $files = array_merge($files, scan_all($path, $rel));
        } else {
            $files[$rel] = filesize($path);
        }
    }
    return $files;
}

$files2027 = scan_all($dir2027);
$filesTarget = scan_all($dirTarget);

echo "Total files in 2027: " . count($files2027) . "\n";
echo "Total files in Target: " . count($filesTarget) . "\n";

// 1. Files in 2027 not in Target
$missing_in_target = array_diff_key($files2027, $filesTarget);
echo "\nFiles in 2027 but NOT in Target (" . count($missing_in_target) . "):\n";
foreach ($missing_in_target as $f => $size) {
    echo "  - $f ($size bytes)\n";
}

// 2. Files present in both, but different sizes
$diff_files = [];
foreach ($files2027 as $f => $s2027) {
    if (isset($filesTarget[$f]) && $filesTarget[$f] !== $s2027) {
        $diff_files[$f] = [
            '2027_size' => $s2027,
            'target_size' => $filesTarget[$f]
        ];
    }
}

echo "\nFiles in both with different sizes (" . count($diff_files) . "):\n";
foreach ($diff_files as $f => $info) {
    echo "  - $f: 2027=" . $info['2027_size'] . "b, Target=" . $info['target_size'] . "b\n";
}
