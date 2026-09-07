<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($file);

$sep = "// ==========================================================================\n// === FEATURES BROUGHT FROM 2027 THEME ===\n// ==========================================================================";

$parts = explode($sep, $content);
$base_content = $parts[0];
$added_content = isset($parts[1]) ? $parts[1] : '';

// Find all functions in base_content
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $base_content, $m_base);
$base_funcs = array_unique($m_base[1]);

// Find all functions in added_content
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $added_content, $m_added);
$added_funcs = array_unique($m_added[1]);

echo "Base functions count: " . count($base_funcs) . "\n";
echo "Added functions count: " . count($added_funcs) . "\n";

$duplicates = array_intersect($base_funcs, $added_funcs);
echo "Duplicate functions:\n";
foreach ($duplicates as $df) {
    echo "  - $df\n";
}

$unique_new = array_diff($added_funcs, $base_funcs);
echo "New unique functions:\n";
foreach ($unique_new as $uf) {
    echo "  + $uf\n";
}
