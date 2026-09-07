<?php
$dir1 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';
$dir2 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir2));
$diffs = [];

foreach ($it as $file) {
    if ($file->isFile()) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dir2)));
        $f1 = $dir1 . $rel;
        $f2 = $file->getPathname();
        
        if (!file_exists($f1)) {
            $diffs[] = [
                'type' => 'ONLY IN 2027',
                'file' => $rel,
                'size2' => filesize($f2)
            ];
        } else {
            $c1 = file_get_contents($f1);
            $c2 = file_get_contents($f2);
            if ($c1 !== $c2) {
                $diffs[] = [
                    'type' => 'DIFFERENT',
                    'file' => $rel,
                    'size1' => strlen($c1),
                    'size2' => strlen($c2),
                    'diff' => strlen($c2) - strlen($c1)
                ];
            }
        }
    }
}

echo "Found " . count($diffs) . " differences:\n";
foreach ($diffs as $d) {
    echo "{$d['type']}: {$d['file']} (size1: " . ($d['size1'] ?? 'N/A') . ", size2: {$d['size2']})\n";
}
