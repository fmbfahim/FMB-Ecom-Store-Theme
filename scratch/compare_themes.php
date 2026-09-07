<?php
$dir2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';
$dirTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';

function get_all_files($dir) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            // Ignore scratch and .git if any
            if (strpos($rel, 'scratch/') === 0 || strpos($rel, '.git/') === 0) continue;
            $files[$rel] = array(
                'size' => $file->getSize(),
                'mtime' => $file->getMTime(),
                'hash' => md5_file($file->getPathname())
            );
        }
    }
    return $files;
}

$files2027 = get_all_files($dir2027);
$filesTarget = get_all_files($dirTarget);

$missing_in_target = array();
$different_files = array();
$identical_files = array();
$extra_in_target = array();

foreach ($files2027 as $rel => $info) {
    if (!isset($filesTarget[$rel])) {
        $missing_in_target[$rel] = $info;
    } else {
        if ($info['hash'] !== $filesTarget[$rel]['hash']) {
            $different_files[$rel] = array(
                '2027_size' => $info['size'],
                'target_size' => $filesTarget[$rel]['size'],
                'size_diff' => $info['size'] - $filesTarget[$rel]['size']
            );
        } else {
            $identical_files[$rel] = $info;
        }
    }
}

foreach ($filesTarget as $rel => $info) {
    if (!isset($files2027[$rel])) {
        $extra_in_target[$rel] = $info;
    }
}

echo "=== COMPARISON REPORT ===\n";
echo "Total files in 2027: " . count($files2027) . "\n";
echo "Total files in Target (fmb-ecom-store): " . count($filesTarget) . "\n";
echo "Identical files: " . count($identical_files) . "\n";
echo "Missing in Target (present in 2027 only): " . count($missing_in_target) . "\n";
echo "Different files: " . count($different_files) . "\n";
echo "Extra in Target (present in fmb-ecom-store only): " . count($extra_in_target) . "\n\n";

if (!empty($missing_in_target)) {
    echo "--- FILES MISSING IN TARGET (PRESENT ONLY IN 2027) ---\n";
    foreach ($missing_in_target as $f => $info) {
        echo " + $f (" . $info['size'] . " bytes)\n";
    }
    echo "\n";
}

if (!empty($different_files)) {
    echo "--- FILES WITH DIFFERENT CONTENT ---\n";
    foreach ($different_files as $f => $d) {
        $sign = $d['size_diff'] > 0 ? "+{$d['size_diff']}" : "{$d['size_diff']}";
        echo " ~ $f (2027: {$d['2027_size']} bytes, Target: {$d['target_size']} bytes, diff: $sign)\n";
    }
    echo "\n";
}

if (!empty($extra_in_target)) {
    echo "--- FILES EXTRA IN TARGET (PRESENT ONLY IN fmb-ecom-store) ---\n";
    foreach ($extra_in_target as $f => $info) {
        echo " * $f (" . $info['size'] . " bytes)\n";
    }
    echo "\n";
}
